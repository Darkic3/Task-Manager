<?php

namespace Tests\Feature;

use App\Http\Controllers\AiChatController;
use App\Models\AiConversation;
use App\Models\AiPendingAction;
use App\Models\AiPlan;
use App\Models\AiSetting;
use App\Models\Task;
use App\Models\User;
use App\Services\AiTooling\ToolError;
use App\Services\AiTooling\ToolPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Idempotency contract for the AI agent:
 * retry / reconnect / double-click / concurrent execution must never
 * create or execute the same operation twice.
 */
class AiIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /** Call the private proposal entry point with an explicit conversation. */
    private function propose(User $user, $conversationId, string $tool, array $args): array
    {
        $controller = app(AiChatController::class);
        $ref = new \ReflectionMethod($controller, 'createPendingFromToolCall');
        $ref->setAccessible(true);

        return $ref->invoke($controller, $user, $conversationId, $tool, $args);
    }

    private function planArgs(string $suffix = ''): array
    {
        return [
            'title' => 'Idem plan'.$suffix,
            'project' => ['name' => 'P'.$suffix, 'tasks' => [['title' => 'T1'.$suffix]]],
        ];
    }

    private function withOpenAi(User $user): void
    {
        AiSetting::create([
            'user_id' => $user->id, 'default_provider' => 'openai',
            'openai_key' => 'sk-test-key', 'openai_model' => 'gpt-4o-mini',
        ]);
    }

    /* ── stable keys ─────────────────────────────────────────── */

    public function test_proposal_key_is_stable_for_identical_requests(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);

        $a = ToolPipeline::validateForProposal($user, 'task_create', ['title' => 'Same'], ['conversation_id' => $conv->id]);
        $b = ToolPipeline::validateForProposal($user, 'task_create', ['title' => 'Same'], ['conversation_id' => $conv->id]);
        $this->assertTrue($a['ok']);
        $this->assertSame($a['idempotency_key'], $b['idempotency_key']);

        // Different conversation → different key (no false dedupe).
        $c = ToolPipeline::validateForProposal($user, 'task_create', ['title' => 'Same'], ['conversation_id' => null]);
        $this->assertNotSame($a['idempotency_key'], $c['idempotency_key']);

        // Plan keys are stable too, and sensitive to content.
        $k1 = ToolPipeline::derivePlanKey($user->id, $conv->id, 'T', ['projects' => []]);
        $k2 = ToolPipeline::derivePlanKey($user->id, $conv->id, 'T', ['projects' => []]);
        $k3 = ToolPipeline::derivePlanKey($user->id, $conv->id, 'T-changed', ['projects' => []]);
        $this->assertSame($k1, $k2);
        $this->assertNotSame($k1, $k3);
        $this->assertSame(64, strlen($k1)); // fits the UNIQUE varchar(64)
    }

    /* ── duplicate proposals ─────────────────────────────────── */

    public function test_duplicate_proposal_reuses_live_pending(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);

        $first = $this->propose($user, $conv->id, 'task_create', ['title' => 'Dedupe me']);
        $this->assertArrayHasKey('action_id', $first);
        $this->assertArrayNotHasKey('error', $first);

        $second = $this->propose($user, $conv->id, 'task_create', ['title' => 'Dedupe me']);
        $this->assertSame($first['action_id'], $second['action_id']);
        $this->assertTrue($second['deduped'] ?? false);

        $this->assertSame(1, AiPendingAction::where('user_id', $user->id)->count());
    }

    public function test_same_args_in_other_conversation_creates_separate_action(): void
    {
        $user = User::factory()->create();
        $a = AiConversation::create(['user_id' => $user->id, 'label' => 'a']);
        $b = AiConversation::create(['user_id' => $user->id, 'label' => 'b']);

        $first = $this->propose($user, $a->id, 'task_create', ['title' => 'Same text']);
        $second = $this->propose($user, $b->id, 'task_create', ['title' => 'Same text']);

        $this->assertNotSame($first['action_id'], $second['action_id']);
        $this->assertSame(2, AiPendingAction::where('user_id', $user->id)->count());
    }

    public function test_duplicate_chat_proposal_over_http_is_deduped(): void
    {
        $user = User::factory()->create();
        $this->withOpenAi($user);

        Http::fake(fn () => Http::response(['choices' => [['message' => [
            'content' => '',
            'tool_calls' => [[
                'id' => 'call_1', 'type' => 'function',
                'function' => ['name' => 'task_create', 'arguments' => json_encode(['title' => 'HTTP dupe'])],
            ]],
        ]]]], 200));

        // Capability routing: the message must expose task_create (a bare
        // "Make it" only exposes the READ-only safe set by design).
        $first = $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'یه تسک بساز به نام HTTP dupe', 'mode' => 'agent'])->assertOk();
        $second = $this->actingAs($user)->postJson(route('ai.chat'), ['message' => 'یه تسک بساز به نام HTTP dupe', 'mode' => 'agent'])->assertOk();

        $this->assertSame($first->json('proposal.action_id'), $second->json('proposal.action_id'));
        $this->assertTrue((bool) $second->json('proposal.deduped'));
        $this->assertSame(1, AiPendingAction::where('user_id', $user->id)->count());
    }

    /* ── retry after terminal states ─────────────────────────── */

    public function test_proposal_after_execution_returns_executed_without_new_row(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);

        $first = $this->propose($user, $conv->id, 'task_create', ['title' => 'Run once']);
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $first['action_id']), [])->assertOk();
        $this->assertSame(1, Task::where('title', 'Run once')->count());

        $retry = $this->propose($user, $conv->id, 'task_create', ['title' => 'Run once']);
        $this->assertSame($first['action_id'], $retry['action_id']);
        $this->assertTrue($retry['already_executed'] ?? false);
        $this->assertSame(1, AiPendingAction::where('user_id', $user->id)->count());
        $this->assertSame(1, Task::where('title', 'Run once')->count());
    }

    public function test_proposal_after_failure_creates_new_pending_via_rotation(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);

        $first = $this->propose($user, $conv->id, 'task_create', ['title' => 'Retryable']);
        $action = AiPendingAction::findOrFail($first['action_id']);
        $action->markFailed('transient boom');

        // Same identical request must NOT 500 on the UNIQUE key: it rotates
        // and records a fresh pending the user can confirm.
        $retry = $this->propose($user, $conv->id, 'task_create', ['title' => 'Retryable']);
        $this->assertArrayNotHasKey('error', $retry);
        $this->assertNotSame($first['action_id'], $retry['action_id']);
        $this->assertSame(2, AiPendingAction::where('user_id', $user->id)->count());

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $retry['action_id']), [])->assertOk();
        $this->assertDatabaseHas('tasks', ['title' => 'Retryable']);
    }

    public function test_proposal_after_expiry_creates_new_row_without_500(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);

        $first = $this->propose($user, $conv->id, 'task_create', ['title' => 'Expired one']);
        AiPendingAction::where('id', $first['action_id'])->update(['expires_at' => now()->subMinute()]);

        $retry = $this->propose($user, $conv->id, 'task_create', ['title' => 'Expired one']);
        $this->assertArrayNotHasKey('error', $retry);
        $this->assertNotSame($first['action_id'], $retry['action_id']);
        $this->assertSame(2, AiPendingAction::where('user_id', $user->id)->count());
    }

    public function test_key_collision_triage_reuses_live_and_rotates_terminal(): void
    {
        $user = User::factory()->create();

        // Live winner → reuse.
        $live = AiPendingAction::create([
            'user_id' => $user->id, 'tool' => 'task_create', 'args' => ['title' => 'L'],
            'preview' => ['title' => 'L', 'rows' => []], 'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15), 'idempotency_key' => str_repeat('a', 64),
        ]);
        $decision = ToolPipeline::resolvePendingKeyCollision($user->id, str_repeat('a', 64));
        $this->assertSame($live->id, $decision['reuse']->id);

        // Terminal owner → rotated fresh key.
        $live->update(['status' => AiPendingAction::STATUS_FAILED, 'error' => 'x']);
        $decision = ToolPipeline::resolvePendingKeyCollision($user->id, str_repeat('a', 64));
        $this->assertArrayHasKey('key', $decision);
        $this->assertNotSame(str_repeat('a', 64), $decision['key']);
    }

    /* ── execution races ─────────────────────────────────────── */

    public function test_double_confirm_over_http_executes_once(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $first = $this->propose($user, $conv->id, 'task_create', ['title' => 'Double click']);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $first['action_id']), [])->assertOk();
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $first['action_id']), [])
            ->assertOk()->assertJson(['deduped' => true]);

        $this->assertSame(1, Task::where('title', 'Double click')->count());
    }

    public function test_confirm_all_twice_creates_tasks_once(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $this->propose($user, $conv->id, 'task_create', ['title' => 'Batch A']);
        $this->propose($user, $conv->id, 'task_create', ['title' => 'Batch B']);

        $this->actingAs($user)->postJson(route('ai.actions.confirm-all'), [])->assertOk();
        $this->actingAs($user)->postJson(route('ai.actions.confirm-all'), [])->assertOk();

        $this->assertSame(1, Task::where('title', 'Batch A')->count());
        $this->assertSame(1, Task::where('title', 'Batch B')->count());
    }

    public function test_reject_cannot_clobber_executed_action(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $first = $this->propose($user, $conv->id, 'task_create', ['title' => 'Keep me']);
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $first['action_id']), [])->assertOk();

        // Late/crossed reject must be a harmless no-op, never revert success.
        $this->actingAs($user)->postJson(route('ai.actions.reject', $first['action_id']), [])
            ->assertOk()->assertJson(['deduped' => true]);
        $this->assertSame(AiPendingAction::STATUS_EXECUTED, AiPendingAction::find($first['action_id'])->status);
        $this->assertDatabaseHas('tasks', ['title' => 'Keep me']);
    }

    public function test_reject_all_leaves_executed_rows_alone(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $done = $this->propose($user, $conv->id, 'task_create', ['title' => 'Done already']);
        $todo = $this->propose($user, $conv->id, 'task_create', ['title' => 'Cancel me']);
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $done['action_id']), [])->assertOk();

        $this->actingAs($user)->postJson(route('ai.actions.reject-all'), [])->assertOk();

        $this->assertSame(AiPendingAction::STATUS_EXECUTED, AiPendingAction::find($done['action_id'])->status);
        $this->assertSame(AiPendingAction::STATUS_REJECTED, AiPendingAction::find($todo['action_id'])->status);
        $this->assertDatabaseHas('tasks', ['title' => 'Done already']);
        $this->assertDatabaseMissing('tasks', ['title' => 'Cancel me']);
    }

    public function test_failed_confirm_is_terminal_and_never_retries_blindly(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Doomed']);
        $first = $this->propose($user, $conv->id, 'task_delete', ['task' => 'Doomed']);
        $this->assertArrayHasKey('action_id', $first);

        // Simulate an execution-side failure (e.g. exception mid-execute):
        // the row is terminal FAILED from now on.
        $action = AiPendingAction::findOrFail($first['action_id']);
        $action->markFailed('simulated execution failure');

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $first['action_id']), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::EXECUTION_ERROR]);
        $this->assertSame(AiPendingAction::STATUS_FAILED, $action->fresh()->status);
    }

    /* ── plans ───────────────────────────────────────────────── */

    public function test_duplicate_plan_proposal_reuses_same_plan(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $args = $this->planArgs();

        $first = $this->propose($user, $conv->id, 'plan_propose', $args);
        $this->assertArrayHasKey('plan', $first);
        $second = $this->propose($user, $conv->id, 'plan_propose', $args);

        $this->assertSame($first['plan']['id'], $second['plan']['id']);
        $this->assertTrue($second['deduped'] ?? false);
        $this->assertSame(1, AiPlan::where('user_id', $user->id)->count());

        // Stable key actually stored (not random per attempt).
        $plan = AiPlan::find($first['plan']['id']);
        $this->assertSame(64, strlen((string) $plan->idempotency_key));
        $again = ToolPipeline::derivePlanKey($user->id, $conv->id, $plan->title, $plan->structure);
        $this->assertSame($plan->idempotency_key, $again);
    }

    public function test_confirm_structure_double_click_confirms_once(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $created = $this->propose($user, $conv->id, 'plan_propose', $this->planArgs(' S'));
        $plan = AiPlan::findOrFail($created['plan']['id']);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertOk()->assertJson(['deduped' => true]);

        // Exactly one approval note: concurrent double-clicks must not double-log.
        $this->assertSame(1, \App\Models\AiMessage::where('conversation_id', $conv->id)
            ->where('content', 'like', '%Structure approved%')->count());
    }

    public function test_full_plan_run_all_twice_builds_once(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $created = $this->propose($user, $conv->id, 'plan_propose', $this->planArgs(' R'));
        $plan = AiPlan::findOrFail($created['plan']['id']);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])->assertOk();
        // Retry / double-click after completion dedupes.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertOk()->assertJson(['deduped' => true]);

        $this->assertSame(1, \App\Models\Project::where('user_id', $user->id)->where('name', 'P R')->count());
        $this->assertSame(1, Task::where('user_id', $user->id)->where('title', 'T1 R')->count());
    }
}
