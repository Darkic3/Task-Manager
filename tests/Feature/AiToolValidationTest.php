<?php

namespace Tests\Feature;

use App\Http\Controllers\AiChatController;
use App\Models\AiConversation;
use App\Models\AiPendingAction;
use App\Models\Note;
use App\Models\Project;
use App\Models\Reminder;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use App\Services\AiProviderService;
use App\Services\AiTooling\ToolError;
use App\Services\AiTooling\ToolPipeline;
use App\Services\AiTooling\ToolSchema;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiToolValidationTest extends TestCase
{
    use RefreshDatabase;

    private function reminderFor(User $user, array $over = []): Reminder
    {
        return Reminder::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Rem',
            'description' => 'd',
            'date' => now()->toDateString(),
            'time' => '09:00',
        ], $over));
    }

    private function propose($user, string $tool, array $args, $conversationId = null): array
    {
        $controller = new AiChatController(app(AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'createPendingFromToolCall');

        return $ref->invoke($controller, $user, $conversationId, $tool, json_encode($args));
    }

    /* ── audit: every tool has a schema ─────────────────────── */

    public function test_every_tool_has_schema_and_risk(): void
    {
        foreach (AiToolService::TOOLS as $tool) {
            $this->assertArrayHasKey($tool, ToolSchema::schemas(), "missing schema for {$tool}");
            $this->assertNotEmpty(AiToolService::riskOf($tool), "missing risk for {$tool}");
        }
        $this->assertCount(22, AiToolService::TOOLS);
    }

    /* ── unknown tool ───────────────────────────────────────── */

    public function test_unknown_tool_is_rejected_with_code(): void
    {
        $user = User::factory()->create();

        $r = ToolPipeline::validateForProposal($user, 'grant_admin', ['user' => 'x']);
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::UNKNOWN_TOOL, $r['code']);

        $svc = new AiToolService;
        $this->assertSame(ToolError::UNKNOWN_TOOL, $svc->validateCall('nope_tool', [], $user)['code']);

        $p = $this->propose($user, 'grant_admin', ['x' => 1]);
        $this->assertArrayHasKey('error', $p);
        $this->assertSame(ToolError::UNKNOWN_TOOL, $p['code']);
    }

    /* ── schema: required / type / enum / date / length ─────── */

    public function test_schema_rejects_bad_shapes(): void
    {
        $user = User::factory()->create();

        // missing required
        $r = ToolPipeline::validateForProposal($user, 'task_create', []);
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::VALIDATION_ERROR, $r['code']);

        // wrong type
        $r = ToolPipeline::validateForProposal($user, 'task_create', ['title' => 123]);
        $this->assertFalse($r['ok']);
        $this->assertSame('title', $r['field']);

        // bad enum
        $r = ToolPipeline::validateForProposal($user, 'task_create', ['title' => 'T', 'priority' => 'urgent']);
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::VALIDATION_ERROR, $r['code']);

        // bad date
        $r = ToolPipeline::validateForProposal($user, 'task_create', ['title' => 'T', 'due_date' => 'not-a-date']);
        $this->assertFalse($r['ok']);

        // bad time
        $r = ToolPipeline::validateForProposal($user, 'reminder_create', ['title' => 'T', 'time' => '25:99']);
        $this->assertFalse($r['ok']);

        // over length
        $r = ToolPipeline::validateForProposal($user, 'task_create', ['title' => str_repeat('a', 256)]);
        $this->assertFalse($r['ok']);

        // bad id
        $r = ToolPipeline::validateForProposal($user, 'task_complete', ['id' => 0]);
        $this->assertFalse($r['ok']);

        // neither id nor title
        $r = ToolPipeline::validateForProposal($user, 'task_complete', ['priority' => 'high']);
        $this->assertFalse($r['ok']);

        // malformed JSON envelope
        $r = ToolPipeline::validateForProposal($user, 'task_create', '{broken json');
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::VALIDATION_ERROR, $r['code']);
    }

    /* ── oversized payload ──────────────────────────────────── */

    public function test_oversized_payload_is_rejected(): void
    {
        $user = User::factory()->create();
        $big = str_repeat('x', ToolSchema::MAX_PAYLOAD_BYTES + 1);

        $r = ToolPipeline::validateForProposal($user, 'task_create', ['title' => 'T', 'description' => $big]);
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::PAYLOAD_TOO_LARGE, $r['code']);

        $p = $this->propose($user, 'note_create', ['title' => 'T', 'content' => $big]);
        $this->assertSame(ToolError::PAYLOAD_TOO_LARGE, $p['code']);
        $this->assertDatabaseCount('ai_pending_actions', 0);
    }

    /* ── ownership ──────────────────────────────────────────── */

    public function test_foreign_resources_are_not_found(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->create(['user_id' => $owner->id, 'project_id' => $project->id]);
        $note = Note::create(['user_id' => $owner->id, 'title' => 'N', 'content' => 'C']);
        $rem = $this->reminderFor($owner);
        $routine = Routine::factory()->create(['user_id' => $owner->id]);

        foreach ([
            ['task_complete', ['id' => $task->id]],
            ['task_delete', ['id' => $task->id]],
            ['note_delete', ['id' => $note->id]],
            ['reminder_complete', ['id' => $rem->id]],
            ['routine_delete', ['id' => $routine->id]],
            ['task_create', ['title' => 'X', 'project_id' => $project->id]],
            ['checklist_toggle', ['id' => 999999]],
        ] as [$tool, $args]) {
            $r = ToolPipeline::validateForProposal($attacker, $tool, $args);
            $this->assertFalse($r['ok'], "expected reject for {$tool}");
            $this->assertSame(ToolError::NOT_FOUND, $r['code'], "expected NOT_FOUND for {$tool}");
        }

        // Existence of foreign rows is never confirmed in messages either.
        $r = ToolPipeline::validateForProposal($attacker, 'task_complete', ['id' => $task->id]);
        $this->assertStringNotContainsString((string) $task->title, $r['error']);
    }

    /* ── state ──────────────────────────────────────────────── */

    public function test_completed_resources_conflict(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        $rem = $this->reminderFor($user, ['is_completed' => true]);

        $r = ToolPipeline::validateForProposal($user, 'task_complete', ['id' => $task->id]);
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::CONFLICT, $r['code']);

        $r = ToolPipeline::validateForProposal($user, 'reminder_complete', ['id' => $rem->id]);
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::CONFLICT, $r['code']);
    }

    public function test_routine_double_complete_conflicts_on_validate_but_exec_stays_idempotent(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        $routine = Routine::factory()->create(['user_id' => $user->id]);

        $first = $svc->execute('routine_complete', ['id' => $routine->id, 'date' => now()->toDateString()], $user);
        $this->assertTrue($first['ok']);
        $this->assertSame(ToolError::OK, $first['code']);

        $v = $svc->validateCall('routine_complete', ['id' => $routine->id], $user);
        $this->assertFalse($v['ok']);
        $this->assertSame(ToolError::CONFLICT, $v['code']);

        // Execute stays idempotent for in-flight repeats.
        $again = $svc->execute('routine_complete', ['id' => $routine->id, 'date' => now()->toDateString()], $user);
        $this->assertTrue($again['ok']);
    }

    public function test_execution_revalidates_vanished_resource(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $ok = ToolPipeline::validateForProposal($user, 'task_delete', ['id' => $task->id]);
        $this->assertTrue($ok['ok']);

        $task->delete(); // vanished between proposal and confirm

        $re = ToolPipeline::revalidateForExecute($user, 'task_delete', ['id' => $task->id]);
        $this->assertFalse($re['ok']);
        $this->assertSame(ToolError::NOT_FOUND, $re['code']);
    }

    public function test_confirm_after_external_delete_fails_structured(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'VanishMe']);

        $p = $this->propose($user, 'task_delete', ['id' => $task->id]);
        $this->assertArrayHasKey('action_id', $p);
        $task->delete();

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $p['action_id']), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::NOT_FOUND]);
    }

    /* ── idempotency / duplicates ───────────────────────────── */

    public function test_identical_proposals_reuse_one_pending(): void
    {
        $user = User::factory()->create();

        $a = $this->propose($user, 'task_create', ['title' => 'Same Task']);
        $b = $this->propose($user, 'task_create', ['title' => 'Same Task']);

        $this->assertArrayHasKey('action_id', $a);
        $this->assertArrayHasKey('action_id', $b);
        $this->assertSame($a['action_id'], $b['action_id']);
        $this->assertTrue($b['deduped'] ?? false);
        $this->assertSame(1, AiPendingAction::where('user_id', $user->id)->where('tool', 'task_create')->count());

        // Derived keys are deterministic per (user, conversation, tool, args).
        $k1 = ToolPipeline::deriveKey($user->id, null, 'task_create', ['title' => 'Same Task']);
        $k2 = ToolPipeline::deriveKey($user->id, null, 'task_create', ['title' => 'Same Task']);
        $k3 = ToolPipeline::deriveKey($user->id, null, 'task_create', ['title' => 'Other Task']);
        $this->assertSame($k1, $k2);
        $this->assertNotSame($k1, $k3);
    }

    public function test_double_confirm_executes_once_with_structured_codes(): void
    {
        $user = User::factory()->create();
        $p = $this->propose($user, 'task_create', ['title' => 'OnceMore']);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $p['action_id']), [])
            ->assertOk()->assertJson(['ok' => true, 'code' => 'ok']);
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $p['action_id']), [])
            ->assertOk()->assertJson(['ok' => true, 'deduped' => true]);

        $this->assertSame(1, Task::where('title', 'OnceMore')->count());
    }

    /* ── loop protection ────────────────────────────────────── */

    public function test_identical_turn_calls_collapse_and_turn_is_capped(): void
    {
        $same = [];
        for ($i = 0; $i < 5; $i++) {
            $same[] = ['name' => 'task_create', 'arguments' => json_encode(['title' => 'Loop'])];
        }
        $r = ToolPipeline::filterTurnCalls($same);
        $this->assertCount(ToolPipeline::MAX_IDENTICAL_PER_TURN, $r['kept']);
        $this->assertCount(2, $r['dropped']);
        $this->assertSame(ToolError::LOOP_DETECTED, $r['dropped'][0]['code']);

        // Distinct calls are NOT a loop — but the turn still has a max.
        $many = [];
        for ($i = 0; $i < ToolPipeline::MAX_TOOL_CALLS_PER_TURN + 5; $i++) {
            $many[] = ['name' => 'task_create', 'arguments' => json_encode(['title' => "Bulk {$i}"]) ];
        }
        $r2 = ToolPipeline::filterTurnCalls($many);
        $this->assertCount(ToolPipeline::MAX_TOOL_CALLS_PER_TURN, $r2['kept']);
        $this->assertCount(5, $r2['dropped']);
        $this->assertSame(ToolError::RATE_LIMITED, $r2['dropped'][0]['code']);
    }

    public function test_stream_turn_guard_drops_loop_duplicates(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $controller = new AiChatController(app(AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'emitToolProposals');

        $accum = [];
        for ($i = 0; $i < 5; $i++) {
            $accum[$i] = ['id' => "c{$i}", 'name' => 'task_create', 'arguments' => json_encode(['title' => 'Loopy'])];
        }

        ob_start();
        $ref->invoke($controller, $accum, $user->id, $conv->id, function () {});
        $out = ob_get_clean();

        // Turn guard keeps 3 identical calls, drops 2 as loop errors — then
        // idempotency collapses the 3 identical kept calls into ONE pending.
        $this->assertSame(1, AiPendingAction::where('user_id', $user->id)->count());
        $this->assertSame(2, substr_count($out, ToolError::LOOP_DETECTED));
    }

    /* ── structured result ──────────────────────────────────── */

    public function test_execute_result_is_structured(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $ok = $svc->execute('task_create', [
            'title' => 'Structured', 'project_id' => null, 'priority' => 'medium', 'status' => 'to_do', 'description' => null,
        ], $user);
        $this->assertTrue($ok['ok']);
        $this->assertSame(ToolError::OK, $ok['code']);
        $this->assertNotEmpty($ok['message']);
        $this->assertNotNull($ok['id']);

        $norm = ToolPipeline::normalizeResult('task_create', ['ok' => false, 'message' => 'boom']);
        $this->assertSame(ToolError::EXECUTION_ERROR, $norm['code']);

        $norm2 = ToolPipeline::normalizeResult('task_create', 'garbage');
        $this->assertFalse($norm2['ok']);
        $this->assertSame(ToolError::EXECUTION_ERROR, $norm2['code']);
    }

    /* ── security stays separate but enforced ───────────────── */

    public function test_security_stage_blocks_privilege_keys_with_its_own_code(): void
    {
        $user = User::factory()->create();

        $r = ToolPipeline::validateForProposal($user, 'task_create', ['title' => 'X', 'user_id' => 999]);
        $this->assertFalse($r['ok']);
        $this->assertSame(ToolError::AUTHORIZATION_ERROR, $r['code']);

        // ...while plain shape problems stay VALIDATION_ERROR.
        $r2 = ToolPipeline::validateForProposal($user, 'task_create', ['title' => '']);
        $this->assertSame(ToolError::VALIDATION_ERROR, $r2['code']);
    }
}
