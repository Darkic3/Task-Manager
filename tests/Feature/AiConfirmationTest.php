<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiPendingAction;
use App\Models\AiPlan;
use App\Models\Task;
use App\Models\User;
use App\Services\AiTooling\ToolError;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function pendingFor(User $user, string $tool, array $resolved, array $over = []): AiPendingAction
    {
        return AiPendingAction::create(array_merge([
            'user_id' => $user->id,
            'tool' => $tool,
            'args' => $resolved,
            'preview' => ['title' => $tool, 'rows' => []],
            'status' => AiPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ], $over));
    }

    /* ── state model ───────────────────────────────────────── */

    public function test_state_model_is_documented_and_terminal(): void
    {
        $this->assertSame('pending', AiPendingAction::STATUS_PENDING);
        $this->assertSame('executing', AiPendingAction::STATUS_EXECUTING);
        $this->assertSame('executed', AiPendingAction::STATUS_EXECUTED);
        $this->assertSame('failed', AiPendingAction::STATUS_FAILED);
        $this->assertSame('rejected', AiPendingAction::STATUS_REJECTED);
        $this->assertSame('expired', AiPendingAction::STATUS_EXPIRED);

        foreach (AiPendingAction::TERMINAL_STATUSES as $s) {
            $a = new AiPendingAction(['status' => $s]);
            $this->assertTrue($a->isTerminal(), "{$s} must be terminal");
            $this->assertFalse($a->isActionable(), "{$s} must not be actionable");
        }
        $this->assertFalse((new AiPendingAction(['status' => 'pending']))->isTerminal());
        $this->assertFalse((new AiPendingAction(['status' => 'executing']))->isTerminal());
    }

    /* ── double / concurrent confirmation ──────────────────── */

    public function test_double_confirmation_executes_once(): void
    {
        $user = User::factory()->create();
        $action = $this->pendingFor($user, 'task_create', ['title' => 'Once', 'priority' => 'medium', 'status' => 'to_do']);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertOk()->assertJson(['ok' => true, 'code' => 'ok']);
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertOk()->assertJson(['ok' => true, 'deduped' => true]);

        $this->assertSame(1, Task::where('title', 'Once')->count());
        $this->assertSame(AiPendingAction::STATUS_EXECUTED, $action->fresh()->status);
    }

    public function test_in_flight_claim_dedupes_instead_of_double_running(): void
    {
        $user = User::factory()->create();
        $action = $this->pendingFor($user, 'task_create', ['title' => 'Race', 'priority' => 'medium', 'status' => 'to_do']);
        // Simulate another worker holding the claim right now.
        $action->update(['status' => AiPendingAction::STATUS_EXECUTING]);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertOk()->assertJson(['ok' => true, 'deduped' => true]);

        $this->assertSame(0, Task::where('title', 'Race')->count());
    }

    public function test_atomic_claim_only_one_winner(): void
    {
        $user = User::factory()->create();
        $action = $this->pendingFor($user, 'task_create', ['title' => 'Claim', 'priority' => 'medium', 'status' => 'to_do']);

        $first = AiPendingAction::where('id', $action->id)->where('status', 'pending')->update(['status' => 'executing']);
        $second = AiPendingAction::where('id', $action->id)->where('status', 'pending')->update(['status' => 'executing']);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
    }

    /* ── expiry / cancellation / foreign ───────────────────── */

    public function test_expired_action_is_marked_and_structured(): void
    {
        $user = User::factory()->create();
        $action = $this->pendingFor($user, 'task_create', ['title' => 'Old'], ['expires_at' => now()->subMinute()]);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::EXPIRED_ACTION]);

        $this->assertSame(AiPendingAction::STATUS_EXPIRED, $action->fresh()->status);
        $this->assertDatabaseMissing('tasks', ['title' => 'Old']);
    }

    public function test_cancelled_action_cannot_execute(): void
    {
        $user = User::factory()->create();
        $action = $this->pendingFor($user, 'task_create', ['title' => 'Nope']);

        $this->actingAs($user)->postJson(route('ai.actions.reject', $action), [])->assertOk();
        $this->assertSame(AiPendingAction::STATUS_REJECTED, $action->fresh()->status);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::CANCELLED]);
        $this->assertDatabaseMissing('tasks', ['title' => 'Nope']);
    }

    public function test_foreign_action_is_forbidden(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $action = $this->pendingFor($victim, 'task_create', ['title' => 'Victim']);

        $this->actingAs($attacker)->postJson(route('ai.actions.confirm', $action), [])->assertStatus(403);
        $this->actingAs($attacker)->postJson(route('ai.actions.undo', $action), [])->assertStatus(403);
        $this->assertDatabaseMissing('tasks', ['title' => 'Victim']);
    }

    /* ── revalidation on state change ──────────────────────── */

    public function test_confirm_after_external_complete_conflicts(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'DoMe']);
        $action = $this->pendingFor($user, 'task_complete', ['id' => $task->id, 'task_title' => 'DoMe']);

        $task->update(['status' => 'completed', 'completed_at' => now()]); // changed since proposal

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::CONFLICT]);
    }

    /* ── execute exception → FAILED, never a zombie ────────── */

    public function test_execute_exception_marks_failed_with_reason(): void
    {
        $user = User::factory()->create();
        $action = $this->pendingFor($user, 'task_create', ['title' => 'Boom', 'priority' => 'medium', 'status' => 'to_do']);

        $mock = \Mockery::mock(AiToolService::class)->makePartial();
        $mock->shouldReceive('execute')->andThrow(new \Exception('db exploded'));
        $this->app->instance(AiToolService::class, $mock);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::EXECUTION_ERROR]);

        $fresh = $action->fresh();
        $this->assertSame(AiPendingAction::STATUS_FAILED, $fresh->status);
        $this->assertStringContainsString('db exploded', (string) $fresh->error);
        $this->assertDatabaseMissing('tasks', ['title' => 'Boom']);

        // Terminal: confirming a failed action never retries blindly.
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::EXECUTION_ERROR]);
    }

    /* ── confirmAll mixed batch ────────────────────────────── */

    public function test_confirm_all_reports_done_expired_failed(): void
    {
        $user = User::factory()->create();
        $this->pendingFor($user, 'task_create', ['title' => 'BatchOne', 'priority' => 'medium', 'status' => 'to_do']);
        $this->pendingFor($user, 'task_create', ['title' => 'BatchTwo', 'priority' => 'medium', 'status' => 'to_do']);
        $this->pendingFor($user, 'task_create', ['title' => 'BatchOld'], ['expires_at' => now()->subMinute()]);
        // Bypasses proposal validation: fails at execution revalidation.
        $this->pendingFor($user, 'task_delete', ['id' => 999999999, 'title' => 'Ghost']);

        $res = $this->actingAs($user)->postJson(route('ai.actions.confirm-all'), [])
            ->assertOk()
            ->assertJson(['ok' => false]);

        $this->assertDatabaseHas('tasks', ['title' => 'BatchOne']);
        $this->assertDatabaseHas('tasks', ['title' => 'BatchTwo']);
        $this->assertDatabaseMissing('tasks', ['title' => 'BatchOld']);
        // Invalid-at-execution stays pending (never force-terminal: the user
        // may restore the resource and retry); expired is marked expired.
        $this->assertSame(1, AiPendingAction::where('user_id', $user->id)->where('status', 'pending')->count());
        $this->assertSame(0, AiPendingAction::where('user_id', $user->id)->whereIn('status', ['executing'])->count());
        $this->assertStringContainsString('2 action(s) executed', $res->json('message'));
        $this->assertStringContainsString('1 expired', $res->json('message'));
        $this->assertStringContainsString('1 failed', $res->json('message'));
    }

    /* ── plan partial failure ──────────────────────────────── */

    public function test_plan_phase_failure_reports_partial_progress(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Partial',
            'project' => ['name' => 'P', 'tasks' => [['title' => 'T1'], ['title' => 'T2']]],
        ], $user);
        $this->assertTrue($check['ok']);
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $plan = AiPlan::create([
            'user_id' => $user->id,
            'conversation_id' => $conv->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_CONFIRMED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        $mock = \Mockery::mock(AiToolService::class)->makePartial();
        $mock->shouldReceive('executePlanPhase')
            ->andReturn(['ok' => true, 'message' => 'Phase one built.'], ['ok' => false, 'message' => 'Phase two exploded.']);
        // serialize()/planRoots()/previewPlan stay real via makePartial.
        $mock->shouldReceive('planRoots')->passthru();
        $this->app->instance(AiToolService::class, $mock);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => 'phase_failed'])
            ->assertJsonPath('completed.0', 'Phase one built.');

        // Partial progress is also persisted to the conversation.
        $this->assertDatabaseHas('ai_messages', [
            'conversation_id' => $conv->id,
            'content' => '⚠ ' . __('Partial progress before failure: :details', ['details' => 'Phase one built.']),
        ]);
    }

    /* ── undo ──────────────────────────────────────────────── */

    public function test_undo_restores_completed_task_once(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'UndoMe', 'status' => 'to_do']);
        $action = $this->pendingFor($user, 'task_complete', ['id' => $task->id, 'task_title' => 'UndoMe']);

        $confirm = $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])->assertOk();
        $confirm->assertJson(['ok' => true, 'undoable' => true]);
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertNotNull($action->fresh()->undo_data);

        $this->actingAs($user)->postJson(route('ai.actions.undo', $action), [])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('to_do', $task->fresh()->status);
        $this->assertNotNull($action->fresh()->undone_at);

        // Second undo dedupes.
        $this->actingAs($user)->postJson(route('ai.actions.undo', $action), [])
            ->assertOk()->assertJson(['deduped' => true]);
    }

    public function test_undo_window_expiry_and_non_reversible(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Old', 'status' => 'to_do']);
        $action = $this->pendingFor($user, 'task_complete', ['id' => $task->id, 'task_title' => 'Old']);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])->assertOk();
        $action->fresh()->update(['executed_at' => now()->subMinutes(11)]);

        $this->actingAs($user)->postJson(route('ai.actions.undo', $action), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::EXPIRED_ACTION]);
        $this->assertSame('completed', $task->fresh()->status);

        // Creates are not reversible.
        $create = $this->pendingFor($user, 'task_create', ['title' => 'Made', 'priority' => 'medium', 'status' => 'to_do']);
        $this->actingAs($user)->postJson(route('ai.actions.confirm', $create), [])->assertOk();
        $this->actingAs($user)->postJson(route('ai.actions.undo', $create), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::VALIDATION_ERROR]);
    }

    public function test_undo_task_update_restores_values(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'title' => 'Before', 'priority' => 'low']);
        $action = $this->pendingFor($user, 'task_update', ['id' => $task->id, 'task_title' => 'Before', 'title' => 'After', 'priority' => 'high']);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])->assertOk();
        $this->assertSame('After', $task->fresh()->title);

        $this->actingAs($user)->postJson(route('ai.actions.undo', $action), [])->assertOk();
        $this->assertSame('Before', $task->fresh()->title);
        $this->assertSame('low', $task->fresh()->priority);
    }

    /* ── prune command ─────────────────────────────────────── */

    public function test_prune_command_expires_and_cleans(): void
    {
        $user = User::factory()->create();
        $stale = $this->pendingFor($user, 'task_create', ['title' => 'Stale'], ['expires_at' => now()->subMinute()]);
        $old = $this->pendingFor($user, 'task_create', ['title' => 'Old']);
        // Query-builder update: Eloquent save() would touch updated_at to now().
        AiPendingAction::where('id', $old->id)->update(['status' => AiPendingAction::STATUS_EXECUTED, 'updated_at' => now()->subDays(31)]);
        $fresh = $this->pendingFor($user, 'task_create', ['title' => 'Fresh']);

        $this->artisan('ai:prune-pendings')->assertSuccessful();

        $this->assertSame(AiPendingAction::STATUS_EXPIRED, $stale->fresh()->status);
        $this->assertDatabaseMissing('ai_pending_actions', ['id' => $old->id]);
        $this->assertSame(AiPendingAction::STATUS_PENDING, $fresh->fresh()->status);
    }

    /* ── backend confirmation policy (LLM never decides) ─────── */

    public function test_policy_read_needs_no_confirmation_but_destructive_sensitive_always_do(): void
    {
        // READ: no confirmation.
        $this->assertTrue(AiToolService::isReadOnly('report_generate'));
        $this->assertFalse(AiToolService::requiresConfirmation('report_generate'));

        // Every DESTRUCTIVE and SENSITIVE tool always needs confirmation.
        foreach (AiToolService::TOOLS as $tool) {
            if (AiToolService::isDestructive($tool) || AiToolService::isSensitive($tool)) {
                $this->assertTrue(AiToolService::requiresConfirmation($tool), "{$tool} must require confirmation");
            }
        }
        $this->assertTrue(AiToolService::isDestructive('task_delete'));
        $this->assertTrue(AiToolService::isDestructive('reminder_delete'));
        $this->assertTrue(AiToolService::isDestructive('note_delete'));
        $this->assertTrue(AiToolService::isDestructive('routine_delete'));
        $this->assertTrue(AiToolService::isSensitive('project_add_member'));
        $this->assertTrue(AiToolService::isSensitive('plan_propose'));

        // State aliases required by the confirmation contract.
        $this->assertSame(AiPendingAction::STATUS_PENDING, AiPendingAction::STATUS_OPEN);
        $this->assertSame(AiPendingAction::STATUS_REJECTED, AiPendingAction::STATUS_CANCELLED);
    }

    public function test_llm_cannot_override_confirmation_policy(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        foreach ([
            ['auto_confirm' => true],
            ['skip_confirmation' => true],
            ['policy' => 'no_confirm'],
            ['confirmation_policy' => 'auto'],
            ['permissions' => 'admin'],
            ['is_admin' => true],
        ] as $smuggled) {
            $r = $svc->validateCall('task_create', array_merge(['title' => 'Sneaky'], $smuggled), $user);
            $this->assertFalse($r['ok'] ?? true, 'policy override must be rejected: '.json_encode($smuggled));
            $this->assertSame(ToolError::AUTHORIZATION_ERROR, $r['code'] ?? null);
        }
        $this->assertDatabaseCount('ai_pending_actions', 0);
    }

    /* ── race: reject vs confirm ─────────────────────────────── */

    public function test_reject_after_execute_is_deduped_never_clobbers(): void
    {
        $user = User::factory()->create();
        $action = $this->pendingFor($user, 'task_create', ['title' => 'WonRace', 'priority' => 'medium', 'status' => 'to_do']);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $action), [])->assertOk();
        $this->assertSame(AiPendingAction::STATUS_EXECUTED, $action->fresh()->status);

        // Late cancel must not flip an executed action back.
        $this->actingAs($user)->postJson(route('ai.actions.reject', $action), [])
            ->assertOk()->assertJson(['ok' => true, 'deduped' => true]);
        $this->assertSame(AiPendingAction::STATUS_EXECUTED, $action->fresh()->status);
        $this->assertSame(1, Task::where('title', 'WonRace')->count());
    }

    public function test_reject_after_reject_is_deduped(): void
    {
        $user = User::factory()->create();
        $action = $this->pendingFor($user, 'task_create', ['title' => 'Twice']);

        $this->actingAs($user)->postJson(route('ai.actions.reject', $action), [])->assertOk();
        $this->actingAs($user)->postJson(route('ai.actions.reject', $action), [])
            ->assertOk()->assertJson(['deduped' => true]);
        $this->assertSame(AiPendingAction::STATUS_REJECTED, $action->fresh()->status);
    }

    /* ── revalidation: tampered / stolen args ────────────────── */

    public function test_confirm_with_tampered_foreign_args_fails_structured(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $victimTask = Task::factory()->create(['user_id' => $victim->id, 'title' => 'VictimTask']);
        // Attacker proposes against their own pending row but with the
        // victim's resource id smuggled into args (post-proposal tamper).
        $action = $this->pendingFor($attacker, 'task_delete', ['id' => $victimTask->id, 'title' => 'VictimTask']);

        $this->actingAs($attacker)->postJson(route('ai.actions.confirm', $action), [])
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'code' => ToolError::NOT_FOUND]);

        $this->assertTrue($victimTask->fresh() !== null || Task::where('id', $victimTask->id)->exists());
        $this->assertNotSame(AiPendingAction::STATUS_EXECUTED, $action->fresh()->status);
    }

    /* ── duplicate execution via retry (idempotency) ─────────── */

    public function test_retry_after_success_reuses_executed_without_new_row(): void
    {
        $user = User::factory()->create();
        $controller = new \App\Http\Controllers\AiChatController(app(\App\Services\AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'createPendingFromToolCall');

        $first = $ref->invoke($controller, $user, null, 'task_create', json_encode(['title' => 'RetrySafe']));
        $this->assertArrayHasKey('action_id', $first);

        $this->actingAs($user)->postJson(route('ai.actions.confirm', $first['action_id']), [])->assertOk();
        $this->assertSame(1, Task::where('title', 'RetrySafe')->count());

        // Retry / reconnect / double-send after success: same derived key →
        // the executed row is surfaced, no second task is ever proposed.
        $retry = $ref->invoke($controller, $user, null, 'task_create', json_encode(['title' => 'RetrySafe']));
        $this->assertTrue($retry['deduped'] ?? false);
        $this->assertTrue($retry['already_executed'] ?? false);
        $this->assertSame($first['action_id'], $retry['action_id']);
        $this->assertSame(1, Task::where('title', 'RetrySafe')->count());
    }

    /* ── confirmAll respects the action limit ────────────────── */

    public function test_confirm_all_respects_max_open_limit(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < AiPendingAction::MAX_OPEN + 5; $i++) {
            $this->pendingFor($user, 'task_create', ['title' => "Cap {$i}", 'priority' => 'medium', 'status' => 'to_do']);
        }

        $this->actingAs($user)->postJson(route('ai.actions.confirm-all'), [])->assertOk();

        // Exactly MAX_OPEN executed; the overflow stays pending for the next batch.
        $this->assertSame(AiPendingAction::MAX_OPEN, Task::where('user_id', $user->id)->count());
        $this->assertSame(5, AiPendingAction::where('user_id', $user->id)->where('status', AiPendingAction::STATUS_PENDING)->count());
    }

    /* ── plan cancel is race-safe ────────────────────────────── */

    public function test_plan_cancel_after_done_is_deduped(): void
    {
        $user = User::factory()->create();
        $plan = AiPlan::create([
            'user_id' => $user->id,
            'title' => 'DonePlan',
            'structure' => ['project' => ['name' => 'P', 'tasks' => []]],
            'phases' => [],
            'status' => AiPlan::STATUS_DONE,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        $this->actingAs($user)->postJson(route('ai.plans.cancel', $plan), [])
            ->assertOk()->assertJson(['ok' => true, 'deduped' => true]);
        $this->assertSame(AiPlan::STATUS_DONE, $plan->fresh()->status);
    }
}
