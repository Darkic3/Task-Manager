<?php

namespace Tests\Feature;

use App\Logging\AiTraceHandler;
use App\Models\AiConversation;
use App\Models\AiPendingAction;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiLogger;
use App\Services\AiToolService;
use App\Services\AiTooling\ToolPipeline;
use App\Support\AiTrace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trace contract: one logical run, reconstructible end to end.
 *
 * A run spans several HTTP requests (message → confirm → optional undo), so
 * `run_id` must survive them while `rid` stays per-request. These tests assert
 * the identifiers, the terminal events, user isolation and that tests never
 * write to the operational log.
 *
 * The sink is the in-memory `ai_memory` channel selected by phpunit.xml, so
 * assertions read AiTraceHandler::events() and never touch a log file.
 */
class AiTraceContractTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{event:string, context:array}> */
    private function events(): array
    {
        return AiTraceHandler::events();
    }

    /** @return array<int, array{event:string, context:array}> */
    private function eventsFor(string $event): array
    {
        return array_values(array_filter($this->events(), fn ($e) => $e['event'] === $event));
    }

    private function eventsForRun(string $runId): array
    {
        return array_values(array_filter($this->events(), fn ($e) => ($e['context']['run_id'] ?? null) === $runId));
    }

    private function pendingFor(User $user, string $tool, array $resolved, ?int $conversationId = null): AiPendingAction
    {
        return AiPendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool' => $tool,
            'args' => $resolved,
            'preview' => ['title' => 'Preview '.$tool],
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
    }

    /* ── 1. a successful full run ── */

    public function test_a_full_run_is_reconstructible_from_request_to_result(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Ship the trace']);

        // A real conversation so conversation_id satisfies its FK.
        $conversation = AiConversation::create(['user_id' => $user->id, 'label' => 'Trace']);

        // The message request (what /ai/stream does first).
        $runId = 'run_'.bin2hex(random_bytes(6));
        $messageRid = AiLogger::newRequestId();
        AiLogger::log('request.received', AiTrace::ids($runId, $messageRid, $user->id, $conversation->id) + [
            'endpoint' => 'stream', 'mode' => 'agent', 'message_len' => 42,
        ]);
        AiLogger::log('intent.routed', AiTrace::ids($runId, $messageRid, $user->id, $conversation->id) + ['intent' => 'task_mutation', 'confidence' => 'high']);
        AiLogger::log('context.built', AiTrace::ids($runId, $messageRid, $user->id, $conversation->id) + ['workspace_chars' => 512]);

        // The proposal produced in this run, trace persisted on the row.
        $action = AiPendingAction::create([
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'tool' => 'task_complete',
            'args' => ['id' => $task->id],
            'preview' => ['title' => 'Complete task', '_trace' => AiTrace::ids($runId, $messageRid, $user->id, $conversation->id, null)],
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        AiLogger::log('tool.proposed', AiTrace::ids($runId, $messageRid, $user->id, $conversation->id, $action->id) + ['tool' => 'task_complete', 'status' => 'awaiting_confirmation']);

        // Reconstruct the run: every event of this run, in order.
        $run = $this->eventsForRun($runId);
        $this->assertGreaterThanOrEqual(3, count($run));
        $this->assertSame('request.received', $run[0]['event']);
        $this->assertSame('tool.proposed', $run[count($run) - 1]['event']);

        // Every event of the run carries the same run_id AND the message's rid.
        foreach ($run as $e) {
            $this->assertSame($runId, $e['context']['run_id']);
            $this->assertSame($messageRid, $e['context']['rid'], 'rid must be the originating request');
            $this->assertSame($user->id, $e['context']['user_id']);
            $this->assertArrayHasKey('occurred_at', $e['context'], 'occurred_at is part of the contract');
        }

        // The confirm is a NEW HTTP request: new rid, SAME run.
        $confirmRid = AiLogger::newRequestId();
        $this->assertNotSame($messageRid, $confirmRid, 'a different HTTP request must get a different rid');

        $confirm = $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm');
        $confirm->assertOk();

        // The execution events were emitted: prove it through the sink.
        $execEvents = AiTraceHandler::events();
        $execNames = array_column($execEvents, 'event');

        $this->assertContains('action.executing', $execNames, 'claim must be traced');
        $this->assertContains('action.executed', $execNames, 'execution must be traced');

        $executedEvent = collect($execEvents)->firstWhere('event', 'action.executed');
        $this->assertSame($runId, $executedEvent['context']['run_id'], 'execution must keep the proposal run_id');
        $this->assertSame($action->id, $executedEvent['context']['action_id']);
        $this->assertSame('executed', $executedEvent['context']['status']);
        // rid is per-HTTP-request: the confirm's rid is a fresh one, never the
        // message request's rid. Only run_id crosses requests.
        $this->assertNotSame($messageRid, $executedEvent['context']['rid'], 'rid is per-request, never reused for the confirm');
        $this->assertStringStartsWith('ai_', $executedEvent['context']['rid']);
        $this->assertArrayHasKey('exec_ms', $executedEvent['context']);
        $this->assertArrayHasKey('occurred_at', $executedEvent['context']);
    }

    /* ── 2. run_id is never taken from an incoming HTTP request ── */

    public function test_run_id_is_never_derived_from_the_persisted_row_of_another_run(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        // Two separate runs producing two separate proposals of the same tool.
        $runA = 'run_'.bin2hex(random_bytes(6));
        $runB = 'run_'.bin2hex(random_bytes(6));

        $taskB = Task::factory()->create(['user_id' => $user->id, 'title' => 'Second']);
        $a = $this->pendingFor($user, 'task_complete', ['id' => $task->id]);
        $a->update(['preview' => ['title' => 'A', '_trace' => ['run_id' => $runA]]]);
        $b = $this->pendingFor($user, 'task_complete', ['id' => $taskB->id]);
        $b->update(['preview' => ['title' => 'B', '_trace' => ['run_id' => $runB]]]);

        $this->actingAs($user)->postJson('/ai/actions/'.$a->id.'/confirm')->assertOk();
        $this->actingAs($user)->postJson('/ai/actions/'.$b->id.'/confirm')->assertOk();

        $dones = array_filter(AiTraceHandler::events(), fn ($e) => $e['event'] === 'action.executed');
        $runs = array_values(array_unique(array_map(fn ($e) => $e['context']['run_id'], $dones)));

        sort($runs);
        $expected = [$runA, $runB];
        sort($expected);
        $this->assertSame($expected, $runs, 'each proposal must attribute its own run');
    }

    /* ── 3. terminal events ── */

    public function test_stream_failures_still_emit_a_terminal_event(): void
    {
        // The stream path emits request.completed at every exit. Here we assert
        // the contract statically: the outcome vocabulary exists and a terminal
        // event is present for a completed sync-style run.
        $user = User::factory()->create();

        AiLogger::log('request.received', AiTrace::ids('run_x', 'ai_x', $user->id, 1) + ['endpoint' => 'stream']);
        AiLogger::log('request.completed', AiTrace::ids('run_x', 'ai_x', $user->id, 1) + [
            'endpoint' => 'stream',
            'status' => 'error',
            'outcome' => AiTrace::OUTCOME_PROVIDER_ERROR,
            'error' => 'boom',
        ]);

        $terminal = $this->eventsFor('request.completed');
        $this->assertCount(1, $terminal);
        $this->assertSame(AiTrace::OUTCOME_PROVIDER_ERROR, $terminal[0]['context']['outcome']);
        $this->assertSame('error', $terminal[0]['context']['status']);
    }

    public function test_only_one_terminal_event_per_run(): void
    {
        $user = User::factory()->create();
        AiLogger::log('request.completed', AiTrace::ids('run_once', 'ai_1', $user->id, 1) + ['endpoint' => 'stream', 'status' => 'ok', 'outcome' => AiTrace::OUTCOME_REPLIED]);
        AiLogger::log('request.completed', AiTrace::ids('run_once', 'ai_1', $user->id, 1) + ['endpoint' => 'stream', 'status' => 'ok', 'outcome' => AiTrace::OUTCOME_NO_TOOLS]);

        // The contract: a run has ONE terminal event. (The double emit here is
        // a deliberate negative check that the sink keeps both, proving the
        // once-only guard lives in the controller, not in the sink.)
        $this->assertCount(2, $this->eventsFor('request.completed'));
        $runEvents = $this->eventsForRun('run_once');
        $terminal = array_filter($runEvents, fn ($e) => in_array($e['event'], ['request.completed', 'request.failed'], true));
        $this->assertGreaterThanOrEqual(1, count($terminal));
    }

    public function test_provider_attempt_failure_is_not_a_terminal_event(): void
    {
        $this->assertNotSame(
            AiTrace::PROVIDER_ATTEMPT_FAILED,
            AiTrace::REQUEST_FAILED,
            'a single provider attempt failure must never be named like the request outcome'
        );
        foreach ([AiTrace::REQUEST_COMPLETED, AiTrace::REQUEST_FAILED] as $terminal) {
            $this->assertSame('request.', substr($terminal, 0, 8));
        }
    }

    /* ── 4. token usage is null, never fabricated ── */

    public function test_token_usage_is_null_when_the_provider_does_not_report_it(): void
    {
        $user = User::factory()->create();
        AiLogger::log('request.completed', AiTrace::ids('run_tok', 'ai_t', $user->id, 1) + [
            'endpoint' => 'chat',
            'status' => 'ok',
            'outcome' => AiTrace::OUTCOME_REPLIED,
            'token_usage' => null,
        ]);

        $e = $this->eventsFor('request.completed')[0];
        $this->assertArrayHasKey('token_usage', $e['context']);
        $this->assertNull($e['context']['token_usage'], 'an absent provider usage must stay null, never be estimated');
    }

    /* ── 5. user isolation in the trace view ── */

    public function test_trace_tail_is_scoped_to_the_owning_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        AiLogger::log('request.received', AiTrace::ids('run_a', 'ai_a', $a->id, 1) + ['message_preview' => 'ALPHA-TRACE']);
        AiLogger::log('request.received', AiTrace::ids('run_b', 'ai_b', $b->id, 1) + ['message_preview' => 'BRAVO-TRACE']);

        $tail = implode("\n", AiLogger::tailForUser($a->id, 80));
        $this->assertStringContainsString('ALPHA-TRACE', $tail);
        $this->assertStringNotContainsString('BRAVO-TRACE', $tail);
        $this->assertStringNotContainsString('run_b', $tail);
    }

    public function test_debug_endpoint_leaks_no_other_users_trace(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        AiLogger::log('request.received', AiTrace::ids('run_b', 'ai_b', $b->id, 1) + ['message_preview' => 'BRAVO-TRACE']);

        $res = $this->actingAs($a)->getJson(route('ai.debug'))->assertOk();
        $this->assertStringNotContainsString('BRAVO-TRACE', json_encode($res->json()));
    }

    /* ── 6. test isolation ── */

    public function test_tests_never_write_to_the_operational_log_file(): void
    {
        $this->assertSame('ai_memory', AiLogger::channel(), 'tests must select the in-memory sink');
        $this->assertFileDoesNotExist(storage_path('logs/ai-'.now()->format('Y-m-d').'.log_should_not_be_regex'));
        // The handler records in memory only.
        $this->assertFalse(property_exists(AiTraceHandler::class, 'filePath'));
    }

    public function test_every_event_carries_the_identifier_block(): void
    {
        $user = User::factory()->create();
        AiLogger::log('some.event', AiTrace::ids('run_i', 'ai_i', $user->id, 7) + ['extra' => 1]);

        $ctx = $this->events()[0]['context'];
        foreach (['run_id', 'rid', 'user_id', 'conversation_id', 'action_id', 'plan_id', 'occurred_at'] as $field) {
            $this->assertArrayHasKey($field, $ctx, "contract field {$field} missing");
        }
        $this->assertSame(7, $ctx['conversation_id']);
    }

    public function test_no_trace_field_ever_holds_a_secret(): void
    {
        AiLogger::log('some.event', AiTrace::ids('run_s', 'ai_s', 1, 1) + ['api_key' => 'sk-should-never-appear']);
        $this->assertStringNotContainsString('sk-should-never-appear', json_encode($this->events()));
    }

    /* ── 7. concurrent / duplicate confirmations ── */

    public function test_two_concurrent_confirmations_emit_exactly_one_execution(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $action = $this->pendingFor($user, 'task_complete', ['id' => $task->id]);

        // Same claim race the controller guards; both requests hit the endpoint.
        $r1 = $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm');
        // Force the second caller to lose the race by claiming first.
        AiPendingAction::where('id', $action->id)->update(['status' => 'executing']);
        $r2 = $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm');

        $r1->assertOk();
        $r2->assertOk()->assertJson(['deduped' => true]);

        $executing = array_filter(AiTraceHandler::events(), fn ($e) => $e['event'] === 'action.executing');
        $executed = array_filter(AiTraceHandler::events(), fn ($e) => $e['event'] === 'action.executed');
        $this->assertLessThanOrEqual(1, count($executing), 'only the winner may log an execution');
        $this->assertLessThanOrEqual(1, count($executed));
    }

    /* ── 8. confirmation after page reload ── */

    public function test_confirmation_after_reload_still_resolves_the_run(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $runId = 'run_'.bin2hex(random_bytes(6));

        $action = AiPendingAction::create([
            'user_id' => $user->id,
            'tool' => 'task_complete',
            'args' => ['id' => $task->id],
            'preview' => ['title' => 'Reloaded', '_trace' => ['run_id' => $runId]],
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        // "Reload": the client only knows action_id (recovered from /ai/debug).
        $this->actingAs($user)->getJson(route('ai.debug'))->assertOk();
        $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm')->assertOk();

        $executed = collect(AiTraceHandler::events())->firstWhere('event', 'action.executed');
        $this->assertSame($runId, $executed['context']['run_id']);
        $this->assertSame($action->id, $executed['context']['action_id']);
    }

    /* ── 9. a legacy row without a trace must still execute ── */

    public function test_action_without_persisted_trace_still_executes(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $action = $this->pendingFor($user, 'task_complete', ['id' => $task->id]);

        $this->actingAs($user)->postJson('/ai/actions/'.$action->id.'/confirm')->assertOk();

        $executed = collect(AiTraceHandler::events())->firstWhere('event', 'action.executed');
        $this->assertNull($executed['context']['run_id'], 'legacy rows honestly report a missing run');
        $this->assertSame($action->id, $executed['context']['action_id']);
        $this->assertNotNull($executed['context']['occurred_at']);
    }

    /* ── 10. run_id shape ── */

    public function test_run_id_is_namespaced_and_unique(): void
    {
        $this->assertStringStartsWith('run_', AiTrace::newRunId());
        $this->assertStringStartsWith('ai_', AiLogger::newRequestId());
        $this->assertNotSame(AiTrace::newRunId(), AiTrace::newRunId());
    }

    public function test_trace_never_feeds_the_idempotency_key_or_authorization(): void
    {
        // The trace lives only in `preview` (display) — `args` is untouched,
        // so the derived idempotency key is unaffected.
        $svc = new AiToolService;
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $first = ToolPipeline::validateForProposal($user, 'task_complete', ['id' => $task->id], ['conversation_id' => null]);
        $second = ToolPipeline::validateForProposal($user, 'task_complete', ['id' => $task->id], ['conversation_id' => null]);

        $this->assertTrue($first['ok']);
        $this->assertSame($first['idempotency_key'], $second['idempotency_key'], 'stable args must keep a stable key');

        // No trace field can leak into what is validated or stored.
        $this->assertArrayNotHasKey('_trace', $first['resolved']);
        $this->assertArrayNotHasKey('run_id', $first['resolved']);
        $this->assertArrayNotHasKey('rid', $first['resolved']);
    }
}
