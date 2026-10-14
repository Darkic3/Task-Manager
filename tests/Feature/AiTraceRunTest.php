<?php

namespace Tests\Feature;

use App\Logging\AiTraceHandler;
use App\Models\AiPendingAction;
use App\Models\AiSetting;
use App\Models\Task;
use App\Models\User;
use App\Services\AiLogger;
use App\Services\AiToolService;
use App\Support\AiTrace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end trace tests: the REAL endpoints (chat + stream + confirm) with a
 * faked provider, asserting the trace contract on what the system actually
 * emitted — nothing here hand-logs an event.
 *
 * Events are read from the in-memory `ai_memory` sink (phpunit.xml), so a
 * test run never touches storage/logs/ai-*.log.
 */
class AiTraceRunTest extends TestCase
{
    use RefreshDatabase;

    private function withOpenAi(User $user): void
    {
        AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);
    }

    /** @return array<int, array{event:string, context:array}> */
    private function trace(): array
    {
        return AiTraceHandler::events();
    }

    /** @return array<int, array{event:string, context:array}> */
    private function traceForRun(string $runId): array
    {
        return array_values(array_filter($this->trace(), fn ($e) => ($e['context']['run_id'] ?? null) === $runId));
    }

    /** @return array<int, string> */
    private function namesForRun(string $runId): array
    {
        return array_column($this->traceForRun($runId), 'event');
    }

    private function contextOf(string $runId, string $event): ?array
    {
        foreach ($this->traceForRun($runId) as $e) {
            if ($e['event'] === $event) {
                return $e['context'];
            }
        }

        return null;
    }

    /** The run_id of the single request the test just made. */
    private function lastRunId(string $endpoint): string
    {
        $received = array_values(array_filter(
            $this->trace(),
            fn ($e) => $e['event'] === 'request.received' && ($e['context']['endpoint'] ?? null) === $endpoint
        ));

        $this->assertNotEmpty($received, "no request.received for {$endpoint}");
        $last = end($received);

        return (string) $last['context']['run_id'];
    }

    private function sseBody(string $payload): string
    {
        return "data: {$payload}\n\n";
    }

    private function chatAgent(User $user, string $message)
    {
        return $this->actingAs($user)->postJson(route('ai.chat'), ['message' => $message, 'mode' => 'agent']);
    }

    private function streamAgent(User $user, string $message)
    {
        return $this->actingAs($user)->post(route('ai.stream'), ['message' => $message, 'mode' => 'agent']);
    }

    /* ── 1. a successful full run: request → proposal ── */

    public function test_chat_run_traces_every_stage_under_one_run_id(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(['choices' => [['message' => [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'Traced task'])],
            ]],
        ]]]], 200));

        $res = $this->chatAgent($user, 'یه تسک بساز به نام Traced task');
        $res->assertOk()->assertJsonPath('proposal.tool', 'task_create');

        $runId = $this->lastRunId('chat');
        $names = $this->namesForRun($runId);

        // The full lifecycle, in order, under ONE run_id.
        $this->assertSame('request.received', $names[0]);
        foreach (['intent.routed', 'context.built', 'tools.routed', 'tool.proposed', 'confirmation.requested', 'request.completed'] as $event) {
            $this->assertContains($event, $names, "missing {$event} in the run chain");
        }
        $this->assertLessThan(array_search('request.completed', $names, true), array_search('tool.proposed', $names, true));
        $this->assertNotContains('request.failed', $names);

        // Identifier block is identical across the whole run.
        foreach ($this->traceForRun($runId) as $e) {
            $this->assertSame($runId, $e['context']['run_id']);
            $this->assertSame($user->id, $e['context']['user_id']);
            $this->assertNotNull($e['context']['rid']);
            $this->assertArrayHasKey('occurred_at', $e['context']);
        }

        // Terminal event states the real outcome: a confirmation was requested.
        $completed = $this->contextOf($runId, 'request.completed');
        $this->assertSame('ok', $completed['status']);
        $this->assertSame(AiTrace::OUTCOME_CONFIRMATION_REQUESTED, $completed['outcome']);
        $this->assertSame(1, $completed['proposal_count']);
        $this->assertIsInt($completed['latency_ms']);
        $this->assertNull($completed['token_usage'], 'no provider usage was reported — must stay null');

        // The proposal event links the exact pending action.
        $proposed = $this->contextOf($runId, 'tool.proposed');
        $actionId = (int) $res->json('proposal.action_id');
        $this->assertSame($actionId, $proposed['action_id']);
        $this->assertSame('awaiting_confirmation', $proposed['status']);

        // Nothing executed from a proposal alone.
        $this->assertDatabaseMissing('tasks', ['title' => 'Traced task']);
    }

    /* ── 2. proposal → confirm: same run, one execution ── */

    public function test_confirm_keeps_the_run_and_executes_exactly_once(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(['choices' => [['message' => [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'Chain task'])],
            ]],
        ]]]], 200));

        $res = $this->chatAgent($user, 'یه تسک بساز به نام Chain task');
        $runId = $this->lastRunId('chat');
        $actionId = (int) $res->json('proposal.action_id');

        $this->actingAs($user)->postJson('/ai/actions/'.$actionId.'/confirm')->assertOk();

        $executing = $this->contextOf($runId, 'action.executing');
        $executed = $this->contextOf($runId, 'action.executed');

        $this->assertNotNull($executing, 'the won claim must be traced');
        $this->assertSame($actionId, $executing['action_id']);
        $this->assertSame('executing', $executing['status']);

        $this->assertNotNull($executed, 'execution must be traced');
        $this->assertSame($runId, $executed['run_id'], 'execution must carry the proposing run_id');
        $this->assertSame($actionId, $executed['action_id']);
        $this->assertSame($user->id, $executed['user_id']);
        $this->assertSame('executed', $executed['status']);
        $this->assertArrayHasKey('exec_ms', $executed);
        $this->assertNotContains(AiTrace::ACTION_FAILED, $this->namesForRun($runId));

        // One real side effect.
        $this->assertSame(1, Task::where('title', 'Chain task')->count());

        // A repeated confirm is a dedupe: no second execution event, no second task.
        $this->actingAs($user)->postJson('/ai/actions/'.$actionId.'/confirm')
            ->assertOk()->assertJson(['deduped' => true]);
        $this->assertSame(1, Task::where('title', 'Chain task')->count());
        $this->assertSame(1, count(array_filter($this->traceForRun($runId), fn ($e) => $e['event'] === 'action.executed')));
    }

    /* ── 3. provider error (sync path) ── */

    public function test_provider_error_ends_the_run_with_the_real_outcome(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(['error' => ['message' => 'quota exceeded']], 500));

        $this->chatAgent($user, 'یه تسک بساز')->assertOk();

        $runId = $this->lastRunId('chat');
        $failed = $this->contextOf($runId, 'request.failed');

        $this->assertNotNull($failed, 'a provider failure must terminate the request event');
        $this->assertSame(AiTrace::OUTCOME_PROVIDER_ERROR, $failed['status']);
        $this->assertSame('provider', $failed['error_type']);
        $this->assertSame(500, $failed['http_status']);
        $this->assertNull($this->contextOf($runId, 'request.completed'), 'failed runs emit no success');
        $this->assertContains('provider.attempt_failed', $this->namesForRun($runId));
        $this->assertDatabaseCount('ai_pending_actions', 0);
    }

    /* ── 4. mid-stream provider error ── */

    public function test_mid_stream_provider_error_is_terminal_and_not_success(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        // Partial content first, then an upstream error body on HTTP 200.
        Http::fake(fn () => Http::response(
            $this->sseBody(json_encode(['choices' => [['delta' => ['content' => 'partial']]]]))
            .$this->sseBody(json_encode(['error' => ['message' => 'upstream exploded']]))
            ."data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream']
        ));

        $this->streamAgent($user, 'یه تسک بساز به نام Never task')->streamedContent();

        $runId = $this->lastRunId('stream');
        $failed = $this->contextOf($runId, 'request.failed');
        $this->assertNotNull($failed);
        $this->assertSame(AiTrace::OUTCOME_PROVIDER_ERROR, $failed['outcome']);
        $this->assertSame('error', $failed['status']);
        $this->assertSame('provider', $failed['error_type']);
        $this->assertNull($this->contextOf($runId, 'request.completed'));
        $this->assertDatabaseCount('ai_pending_actions', 0);
    }

    /* ── 4b. total outage: stream AND sync fallback both fail ── */

    public function test_total_provider_outage_is_failed_not_completed(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        // Every transport attempt times out: the stream opener and the
        // sync fallback behind it. The user still gets a streamed error
        // explanation, but the terminal event must be failed, never ok.
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'));

        $content = $this->streamAgent($user, 'یه تسک بساز به نام Never task')->streamedContent();

        $runId = $this->lastRunId('stream');
        $failed = $this->contextOf($runId, 'request.failed');
        $this->assertNotNull($failed, 'a total outage must terminate the request event as failed');
        $this->assertSame(AiTrace::OUTCOME_PROVIDER_ERROR, $failed['outcome']);
        $this->assertSame('error', $failed['status']);
        $this->assertSame('provider', $failed['error_type']);
        $this->assertSame('sync_fallback', $failed['recovered']);
        $this->assertNull($this->contextOf($runId, 'request.completed'), 'failed runs emit no success');
        $this->assertContains('provider.attempt_failed', $this->namesForRun($runId));
        $this->assertDatabaseCount('ai_pending_actions', 0);
        $this->assertNotEmpty($content, 'the user still receives a streamed explanation');
    }

    /* ── 5. request without tools ── */

    public function test_reply_only_request_reports_no_tools_outcome(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        // One fake for both transports: Laravel appends bare Http::fake()
        // stubs and the FIRST match wins, so a second fake() would never
        // take effect. Branch on the payload's stream flag instead.
        Http::fake(function ($request) {
            $payload = $request->data();
            if (! empty($payload['stream'])) {
                return Http::response(
                    'data: '.json_encode(['choices' => [['delta' => ['content' => 'hi']]]])."\n\n".'data: [DONE]'."\n\n",
                    200,
                    ['Content-Type' => 'text/event-stream']
                );
            }

            return Http::response(['choices' => [['message' => ['content' => 'Just a reply']]]], 200);
        });

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'سلام', 'mode' => 'chat'])->assertOk();

        $completed = $this->contextOf($this->lastRunId('chat'), 'request.completed');
        $this->assertSame(AiTrace::OUTCOME_REPLIED, $completed['outcome']);
        $this->assertSame(0, $completed['proposal_count']);
        $this->assertDatabaseCount('ai_pending_actions', 0);

        // The stream transport names the same reality no_tools.
        $this->streamAgent($user, 'یه تسک بساز به نام Ignored')->streamedContent();

        $streamCompleted = $this->contextOf($this->lastRunId('stream'), 'request.completed');
        $this->assertSame(AiTrace::OUTCOME_NO_TOOLS, $streamCompleted['outcome']);
        $this->assertSame(0, $streamCompleted['proposal_count']);
        $this->assertTrue($streamCompleted['done_marker']);
    }

    /* ── 6. stream run: proposal + confirmation events ── */

    public function test_stream_run_traces_proposal_and_confirmation(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(
            $this->sseBody(json_encode(['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0, 'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'SSE task'])],
            ]]]]]]))."data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream']
        ));

        $res = $this->streamAgent($user, 'یه تسک بساز به نام SSE task');
        $res->assertOk();
        $this->assertStringContainsString('tool_proposal', $res->streamedContent());

        $runId = $this->lastRunId('stream');
        $names = $this->namesForRun($runId);
        foreach (['request.received', 'intent.routed', 'context.built', 'tools.routed', 'stream.tool_calls_received', 'tool.proposed', 'confirmation.requested', 'request.completed'] as $event) {
            $this->assertContains($event, $names, "missing {$event} in the stream chain");
        }

        $completed = $this->contextOf($runId, 'request.completed');
        $this->assertSame(AiTrace::OUTCOME_CONFIRMATION_REQUESTED, $completed['outcome']);
        $this->assertSame(1, $completed['proposal_count']);
        $this->assertSame(1, $completed['tool_calls']);
        $this->assertTrue($completed['done_marker']);

        // The pending row itself carries the trace, so a later confirm can
        // attribute the execution to this run.
        $action = AiPendingAction::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($runId, $action->preview['_trace']['run_id']);
        $this->assertSame($user->id, $action->preview['_trace']['user_id']);

        $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm')->assertOk();
        $executed = $this->contextOf($runId, 'action.executed');
        $this->assertSame($runId, $executed['run_id']);
        $this->assertSame($action->id, $executed['action_id']);
        $this->assertSame(1, Task::where('title', 'SSE task')->count());
    }

    /* ── 7. failed execution ── */

    public function test_failed_execution_is_traced_with_the_run_and_no_double_run(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(['choices' => [['message' => [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'Doomed task'])],
            ]],
        ]]]], 200));

        $res = $this->chatAgent($user, 'یه تسک بساز به نام Doomed task');
        $runId = $this->lastRunId('chat');
        $actionId = (int) $res->json('proposal.action_id');

        $mock = \Mockery::mock(AiToolService::class)->makePartial();
        $mock->shouldReceive('execute')->andThrow(new \Exception('db exploded'));
        $this->app->instance(AiToolService::class, $mock);

        $this->actingAs($user)->postJson('/ai/actions/'.$actionId.'/confirm')->assertStatus(422);

        $names = $this->namesForRun($runId);
        $this->assertContains('action.executing', $names, 'the claim was won before the failure');
        $this->assertContains(AiTrace::ACTION_FAILED, $names);

        $failed = $this->contextOf($runId, AiTrace::ACTION_FAILED);
        $this->assertSame($runId, $failed['run_id']);
        $this->assertSame($actionId, $failed['action_id']);
        $this->assertSame('failed', $failed['status']);
        $this->assertNull($this->contextOf($runId, 'action.executed'));
        $this->assertDatabaseMissing('tasks', ['title' => 'Doomed task']);

        // Terminal failure is never blindly retried: the second confirm does
        // not execute and does not emit a new execution event.
        $this->actingAs($user)->postJson('/ai/actions/'.$actionId.'/confirm')->assertStatus(422);
        $this->assertSame(1, count(array_filter($this->traceForRun($runId), fn ($e) => $e['event'] === AiTrace::ACTION_FAILED)));
        $this->assertDatabaseMissing('tasks', ['title' => 'Doomed task']);
    }

    /* ── 8. retry of the same operation ── */

    public function test_retry_of_the_same_operation_keeps_its_trace_and_one_side_effect(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(['choices' => [['message' => [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'RetrySafe'])],
            ]],
        ]]]], 200));

        $first = $this->chatAgent($user, 'یه تسک بساز به نام RetrySafe');
        $firstRun = $this->lastRunId('chat');
        $actionId = (int) $first->json('proposal.action_id');

        // Same operation, a NEW logical run (the user re-sent the message).
        $second = $this->chatAgent($user, 'یه تسک بساز به نام RetrySafe');
        $secondRun = $this->lastRunId('chat');
        $this->assertNotSame($firstRun, $secondRun, 'a re-sent message is a new logical run');

        // The retry surfaces the SAME pending action, never a duplicate.
        $this->assertSame($actionId, (int) $second->json('proposal.action_id'));
        $deduped = $this->contextOf($secondRun, 'tool.proposal_deduped');
        $this->assertNotNull($deduped, 'the retry must be traced as deduped');
        $this->assertSame($actionId, $deduped['action_id']);
        $this->assertSame($secondRun, $deduped['run_id']);
        $this->assertSame(1, AiPendingAction::where('user_id', $user->id)->count());

        // Confirm twice → one execution, one task, one executed event.
        $this->actingAs($user)->postJson('/ai/actions/'.$actionId.'/confirm')->assertOk();
        $this->actingAs($user)->postJson('/ai/actions/'.$actionId.'/confirm')->assertOk()->assertJson(['deduped' => true]);
        $this->assertSame(1, Task::where('title', 'RetrySafe')->count());
        $this->assertSame(1, count(array_filter($this->traceForRun($firstRun), fn ($e) => $e['event'] === 'action.executed')));
    }

    /* ── 9. concurrent requests ── */

    public function test_concurrent_requests_keep_their_runs_and_users_separate(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->withOpenAi($a);
        $this->withOpenAi($b);

        Http::fake(fn () => Http::response(
            $this->sseBody(json_encode(['choices' => [['delta' => ['content' => 'ok']]]]))."data: [DONE]\n\n",
            200,
            ['Content-Type' => 'text/event-stream']
        ));

        // Interleaved requests, no shared state between them.
        $this->streamAgent($a, 'یه تسک بساز به نام A')->streamedContent();
        $this->streamAgent($b, 'یه تسک بساز به نام B')->streamedContent();

        $runA = $this->lastRunIdForUser('stream', $a->id);
        $runB = $this->lastRunIdForUser('stream', $b->id);
        $this->assertNotSame($runA, $runB);

        foreach ($this->traceForRun($runA) as $e) {
            $this->assertSame($a->id, $e['context']['user_id'], 'run A must never carry run B user');
            $this->assertSame($runA, $e['context']['run_id']);
        }
        foreach ($this->traceForRun($runB) as $e) {
            $this->assertSame($b->id, $e['context']['user_id']);
        }

        // Each run has exactly one terminal event.
        foreach ([$runA, $runB] as $runId) {
            $terminals = array_filter($this->traceForRun($runId), fn ($e) => in_array($e['event'], ['request.completed', 'request.failed'], true));
            $this->assertCount(1, $terminals, "run {$runId} must have exactly one terminal event");
        }

        // Each user only ever sees their own tail.
        $tailA = implode("\n", AiLogger::tailForUser($a->id, 80));
        $this->assertStringContainsString($runA, $tailA);
        $this->assertStringNotContainsString($runB, $tailA);
    }

    private function lastRunIdForUser(string $endpoint, int $userId): string
    {
        $received = array_values(array_filter(
            $this->trace(),
            fn ($e) => $e['event'] === 'request.received'
                && ($e['context']['endpoint'] ?? null) === $endpoint
                && ($e['context']['user_id'] ?? null) === $userId
        ));
        $this->assertNotEmpty($received);

        return (string) end($received)['context']['run_id'];
    }

    /* ── 10. truncated stream ── */

    public function test_truncated_stream_reports_truncation_not_success(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        // The provider closed the body without [DONE].
        Http::fake(fn () => Http::response(
            $this->sseBody(json_encode(['choices' => [['delta' => ['content' => 'cut off']]]])),
            200,
            ['Content-Type' => 'text/event-stream']
        ));

        $this->streamAgent($user, 'یه تسک بساز به نام Cut task')->streamedContent();

        $completed = $this->contextOf($this->lastRunId('stream'), 'request.completed');
        $this->assertNotNull($completed);
        $this->assertSame(AiTrace::OUTCOME_STREAM_TRUNCATED, $completed['outcome'], 'a body cut before [DONE] is not a success');
        $this->assertFalse($completed['done_marker']);
        $this->assertSame(0, $completed['error_count']);
    }

    /* ── 11. confirmation after a page reload ── */

    public function test_confirmation_after_reload_resolves_the_original_run(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(['choices' => [['message' => [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'Reloaded task'])],
            ]],
        ]]]], 200));

        $res = $this->chatAgent($user, 'یه تسک بساز به نام Reloaded task');
        $runId = $this->lastRunId('chat');
        $actionId = (int) $res->json('proposal.action_id');

        // "Reload": the client recovers the pending list from /ai/debug …
        $debug = $this->actingAs($user)->getJson(route('ai.debug'))->assertOk();
        $this->assertSame($actionId, $debug->json('recent_pending_actions.0.id'));

        // … and confirms in a brand-new HTTP request.
        $this->actingAs($user)->postJson('/ai/actions/'.$actionId.'/confirm')->assertOk();

        $executed = $this->contextOf($runId, 'action.executed');
        $this->assertNotNull($executed, 'the stored trace must survive the reload');
        $this->assertSame($runId, $executed['run_id']);
        $this->assertSame($actionId, $executed['action_id']);
        $this->assertNotSame($this->contextOf($runId, 'tool.proposed')['rid'], $executed['rid'], 'rid is per-request; only run_id crosses the reload');
        $this->assertSame(1, Task::where('title', 'Reloaded task')->count());
    }

    /* ── 12. no_provider offline fallback still terminates ── */

    public function test_offline_fallback_run_reports_its_own_outcome(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post(route('ai.stream'), ['message' => 'سلام', 'mode' => 'agent']);
        $res->assertOk();
        $this->assertStringContainsString('lina-offline', $res->streamedContent());

        $runId = $this->lastRunId('stream');
        $names = $this->namesForRun($runId);
        $this->assertContains('request.offline_fallback', $names);
        $this->assertContains('request.completed', $names);

        $completed = $this->contextOf($runId, 'request.completed');
        $this->assertSame(AiTrace::OUTCOME_OFFLINE_FALLBACK, $completed['outcome']);
        $this->assertNull($completed['token_usage']);
    }

    /* ── 13. token usage only when the provider reports it ── */

    public function test_token_usage_is_recorded_only_when_reported(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        // Provider reports usage → it is recorded verbatim.
        // One stateful fake: bare Http::fake() stubs append and the FIRST
        // match wins, so a second fake() would never take effect.
        $providerCalls = 0;
        Http::fake(function () use (&$providerCalls) {
            $providerCalls++;
            if ($providerCalls === 1) {
                return Http::response([
                    'choices' => [['message' => ['content' => 'reply']]],
                    'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 34, 'total_tokens' => 154],
                ], 200);
            }

            // Provider reports nothing → the chat transport stays null.
            return Http::response(['choices' => [['message' => ['content' => 'reply']]]], 200);
        });

        $this->chatAgent($user, 'یه تسک بساز');
        $runId = $this->lastRunId('chat');
        $toolCalls = $this->contextOf($runId, 'provider.tool_calls');
        $this->assertSame(['prompt_tokens' => 120, 'completion_tokens' => 34, 'total_tokens' => 154], $toolCalls['token_usage']);

        $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'سلام', 'mode' => 'chat'])->assertOk();

        $completed = $this->contextOf($this->lastRunId('chat'), 'request.completed');
        $this->assertNull($completed['token_usage']);
    }
}
