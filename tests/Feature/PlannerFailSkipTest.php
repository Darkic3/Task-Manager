<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineChecklistItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerFailSkipTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $user, string $title, array $attrs = []): Task
    {
        $project = Project::factory()->create(['user_id' => $user->id]);

        return Task::factory()->create(array_merge([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => $title,
            'due_date' => now()->toDateString(),
            'status' => 'to_do',
        ], $attrs));
    }

    private function nextUpHtml(User $user): string
    {
        return $this->actingAs($user)->getJson(
            route('planner.next-up', ['date' => now()->toDateString()])
        )->assertOk()->json('html');
    }

    private function dayHtml(User $user): string
    {
        return $this->actingAs($user)->get(
            route('planner.index', ['view' => 'day'])
        )->assertOk()->getContent();
    }

    /* ── Routine step skip ── */

    public function test_step_skip_marks_not_done_and_undoes(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $step = RoutineChecklistItem::create([
            'user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Pose', 'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('planner.check-items.skip', $step), ['date' => now()->toDateString()])
            ->assertOk()->assertJson(['ok' => true, 'skipped' => true]);

        $this->assertTrue($step->fresh()->skippedOn(now()));
        $this->assertFalse($step->fresh()->completedOn(now()));

        // Undo.
        $this->actingAs($user)
            ->postJson(route('planner.check-items.skip', $step), ['date' => now()->toDateString()])
            ->assertOk()->assertJson(['ok' => true, 'skipped' => false]);

        $this->assertFalse($step->fresh()->skippedOn(now()));
    }

    public function test_skipped_step_never_completes_the_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $first = RoutineChecklistItem::create([
            'user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'First', 'sort_order' => 0,
        ]);
        $second = RoutineChecklistItem::create([
            'user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Second', 'sort_order' => 1,
        ]);

        $this->actingAs($user)->postJson(route('planner.check-items.skip', $first))->assertOk();
        $this->actingAs($user)->postJson(route('planner.check-items.toggle', $second))->assertOk();

        $this->assertFalse($routine->fresh()->completedOn(now()));
    }

    public function test_ticking_a_skipped_step_converts_it_to_done(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $step = RoutineChecklistItem::create([
            'user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Pose', 'sort_order' => 0,
        ]);

        $this->actingAs($user)->postJson(route('planner.check-items.skip', $step))->assertOk();
        $this->actingAs($user)->postJson(route('planner.check-items.toggle', $step))
            ->assertOk()->assertJsonPath('completed', true);

        $this->assertTrue($step->fresh()->completedOn(now()));
        $this->assertFalse($step->fresh()->skippedOn(now()));
    }

    public function test_next_up_moves_past_a_skipped_step(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Stretch',
        ]);
        $first = RoutineChecklistItem::create([
            'user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Alpha pose', 'sort_order' => 0,
        ]);
        RoutineChecklistItem::create([
            'user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Beta pose', 'sort_order' => 1,
        ]);

        $this->actingAs($user)->postJson(route('planner.check-items.skip', $first))->assertOk();

        $html = $this->nextUpHtml($user);
        // Guidance moved past the skipped step.
        $this->assertStringContainsString('data-step-name="Beta pose"', $html);
        $this->assertStringNotContainsString('data-step-name="Alpha pose"', $html);
    }

    public function test_avoid_step_skip_is_rejected(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid',
        ]);
        $step = RoutineChecklistItem::create([
            'user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Slot', 'sort_order' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('planner.check-items.skip', $step))
            ->assertStatus(422);
    }

    public function test_day_renders_step_skip_buttons_and_routine_modal_footer(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily',
            'tracking_mode' => 'sets', 'title' => 'Big tracked',
        ]);
        for ($i = 0; $i < 6; $i++) {
            RoutineChecklistItem::create([
                'user_id' => $user->id, 'routine_id' => $routine->id,
                'name' => 'Move '.$i, 'sort_order' => $i, 'target_sets' => 1,
            ]);
        }

        $html = $this->dayHtml($user);

        $this->assertStringContainsString('data-step-skip', $html);
        $this->assertStringContainsString('plRoutineModal', $html);
        $this->assertStringContainsString('data-modal-foot', $html);
        $this->assertStringContainsString('data-modal-done', $html);
        $this->assertStringContainsString('data-modal-skip', $html);
    }

    /* ── Task fail ── */

    public function test_task_fail_records_note_and_renders_red(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'Write report');

        $this->actingAs($user)->postJson(route('planner.tasks.fail', $task), [
            'note' => 'No energy left',
        ])->assertOk()->assertJson(['ok' => true, 'failed' => true, 'rescheduled' => false]);

        $fresh = $task->fresh();
        $this->assertNotNull($fresh->failed_at);
        $this->assertSame('No energy left', $fresh->fail_note);

        $html = $this->dayHtml($user);
        $this->assertStringContainsString('is-failed', $html);
        $this->assertStringContainsString('No energy left', $html);
        $this->assertStringContainsString('data-fail-task', $html);
    }

    public function test_task_fail_with_reschedule_moves_the_due_date(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'Write report');
        $target = now()->addDays(3)->toDateString();

        $this->actingAs($user)->postJson(route('planner.tasks.fail', $task), [
            'note' => 'Out of time',
            'reschedule_date' => $target,
        ])->assertOk()->assertJson(['ok' => true, 'rescheduled' => true]);

        $this->assertSame($target, $task->fresh()->due_date->toDateString());
        $this->assertNotNull($task->fresh()->failed_at);
    }

    public function test_task_unfail_clears_the_marker(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'Write report');

        $this->actingAs($user)->postJson(route('planner.tasks.fail', $task), ['note' => 'x'])->assertOk();
        $this->actingAs($user)->postJson(route('planner.tasks.unfail', $task))
            ->assertOk()->assertJson(['ok' => true, 'failed' => false]);

        $fresh = $task->fresh();
        $this->assertNull($fresh->failed_at);
        $this->assertNull($fresh->fail_note);
    }

    public function test_failed_task_is_not_suggested_next_up(): void
    {
        $user = User::factory()->create();
        $failed = $this->task($user, 'Doomed draft', ['priority' => 'high']);
        $this->task($user, 'Healthy draft', ['priority' => 'medium']);

        $this->actingAs($user)->postJson(route('planner.tasks.fail', $failed))->assertOk();

        $html = $this->nextUpHtml($user);
        $this->assertStringContainsString('Healthy draft', $html);
        $this->assertStringNotContainsString('Doomed draft', $html);
    }

    public function test_completing_a_failed_task_clears_the_fail(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'Write report');

        $this->actingAs($user)->postJson(route('planner.tasks.fail', $task))->assertOk();
        $this->actingAs($user)->postJson(route('planner.tasks.toggle', $task))->assertOk();

        $fresh = $task->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertNull($fresh->failed_at);
    }

    public function test_completed_task_cannot_be_failed(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'Write report', ['status' => 'completed']);

        $this->actingAs($user)
            ->postJson(route('planner.tasks.fail', $task))
            ->assertStatus(422);
    }

    public function test_fail_rejects_other_users_tasks(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $task = $this->task($owner, 'Secret');

        $this->actingAs($intruder)
            ->postJson(route('planner.tasks.fail', $task))
            ->assertForbidden();
    }

    /* ── Tracked-routine completion guard ── */

    private function setsRoutine(User $user, string $title = 'Cobra'): array
    {
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily',
            'title' => $title, 'tracking_mode' => 'sets',
        ]);
        $steps = [];
        foreach (['Morning lift', 'Noon lift'] as $i => $name) {
            $steps[] = RoutineChecklistItem::create([
                'user_id' => $user->id, 'routine_id' => $routine->id,
                'name' => $name, 'sort_order' => $i, 'target_sets' => 1,
                'time_period' => $i === 0 ? 'morning' : 'noon',
            ]);
        }

        return [$routine, $steps];
    }

    public function test_whole_routine_tick_refused_without_logged_sets(): void
    {
        $user = User::factory()->create();
        [$routine] = $this->setsRoutine($user);

        $this->actingAs($user)
            ->postJson(route('planner.routines.toggle', $routine), ['date' => now()->toDateString()])
            ->assertStatus(422);

        $this->assertFalse($routine->fresh()->completedOn(now()));
        $this->assertSame(0, $routine->completions()->count());
    }

    public function test_whole_routine_tick_allowed_once_every_step_logged(): void
    {
        $user = User::factory()->create();
        [$routine, $steps] = $this->setsRoutine($user);
        $date = now()->toDateString();

        // One logged set per step: steps tick, routine stays open (full guard
        // needs ≥1 log per step — satisfied here).
        foreach ($steps as $step) {
            $this->actingAs($user)->postJson(
                route('planner.routines.log', $routine).'?date='.$date,
                ['item_id' => $step->id, 'sets' => ['1' => 15]]
            )->assertOk();
        }

        // Routine auto-completed via full logs; uncheck then re-check to
        // exercise the guarded complete path with logs present.
        $this->actingAs($user)
            ->postJson(route('planner.routines.toggle', $routine), ['date' => $date])
            ->assertOk()->assertJsonPath('completed', false);
        $this->actingAs($user)
            ->postJson(route('planner.routines.toggle', $routine), ['date' => $date])
            ->assertOk()->assertJsonPath('completed', true);
    }

    public function test_whole_routine_tick_refused_for_value_routine_without_value(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily',
            'tracking_mode' => 'value', 'value_kind' => 'number',
        ]);

        $this->actingAs($user)
            ->postJson(route('planner.routines.toggle', $routine), ['date' => now()->toDateString()])
            ->assertStatus(422);

        $this->assertFalse($routine->fresh()->completedOn(now()));

        // Logging the value completes it through the legit path.
        $this->actingAs($user)->postJson(
            route('planner.routines.log', $routine).'?date='.now()->toDateString(),
            ['value' => 72]
        )->assertOk()->assertJsonPath('routine_completed', true);
    }

    public function test_repair_removes_unlogged_completions_only(): void
    {
        $user = User::factory()->create();
        [$routine, $steps] = $this->setsRoutine($user);
        $date = now()->toDateString();

        // Bogus rows as the bare-tick path used to create them: done flags
        // with zero logged numbers.
        $steps[0]->completions()->create([
            'user_id' => $user->id, 'completed_date' => $date,
            'completed_at' => now(), 'status' => 'done',
        ]);
        $routine->completions()->create([
            'user_id' => $user->id, 'routine_id' => $routine->id,
            'completed_date' => $date, 'completed_at' => now(), 'status' => 'done',
        ]);
        // A skipped step must survive the repair.
        $steps[1]->skipOn($date);

        // A legit logged step on another routine must survive too.
        [$okRoutine, $okSteps] = $this->setsRoutine($user, 'Honest');
        $this->actingAs($user)->postJson(
            route('planner.routines.log', $okRoutine).'?date='.$date,
            ['item_id' => $okSteps[0]->id, 'sets' => ['1' => 10]]
        )->assertOk();

        $stats = \App\Services\RoutineCompletionRepair::run();

        $this->assertSame(1, $stats['steps']);
        $this->assertSame(1, $stats['routines']);
        $this->assertFalse($steps[0]->fresh()->completedOn($date));
        $this->assertFalse($routine->fresh()->completedOn($date));
        $this->assertTrue($steps[1]->fresh()->skippedOn($date));
        $this->assertTrue($okSteps[0]->fresh()->completedOn($date));
    }

    public function test_ticking_all_steps_never_completes_value_routine_without_value(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily',
            'tracking_mode' => 'value', 'value_kind' => 'number',
        ]);
        $steps = [];
        foreach (['A', 'B'] as $i => $name) {
            $steps[] = RoutineChecklistItem::create([
                'user_id' => $user->id, 'routine_id' => $routine->id,
                'name' => $name, 'sort_order' => $i,
            ]);
        }

        foreach ($steps as $step) {
            $this->actingAs($user)->postJson(route('planner.check-items.toggle', $step))
                ->assertOk();
        }

        // Steps tick (checkbox semantics) but the routine stays open: no value logged.
        $this->assertFalse($routine->fresh()->completedOn(now()));
    }

    /* ── All-steps-skipped closes the routine ── */

    private function threeStepRoutine(User $user): array
    {
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Trio',
        ]);
        $steps = [];
        foreach (['One', 'Two', 'Three'] as $i => $name) {
            $steps[] = RoutineChecklistItem::create([
                'user_id' => $user->id, 'routine_id' => $routine->id,
                'name' => $name, 'sort_order' => $i,
            ]);
        }

        return [$routine, $steps];
    }

    public function test_skipping_all_steps_auto_skips_the_routine(): void
    {
        $user = User::factory()->create();
        [$routine, $steps] = $this->threeStepRoutine($user);

        foreach ([$steps[0], $steps[1]] as $step) {
            $this->actingAs($user)->postJson(route('planner.check-items.skip', $step))
                ->assertOk()->assertJson(['ok' => true, 'routine_skipped' => false]);
        }
        $this->assertNull(
            $routine->fresh()->completions()->where('completed_date', now()->toDateString())->first()
        );

        $this->actingAs($user)->postJson(route('planner.check-items.skip', $steps[2]))
            ->assertOk()->assertJson([
                'ok' => true, 'routine_skipped' => true,
                'steps_skipped' => 3, 'steps_total' => 3,
            ]);

        $record = $routine->fresh()->completions()->where('completed_date', now()->toDateString())->first();
        $this->assertNotNull($record);
        $this->assertSame('skipped', $record->status);

        // The closed day renders without a reload and stays out of Next Up.
        $html = $this->dayHtml($user);
        $this->assertStringContainsString('is-skipped', $html);
        $this->assertStringNotContainsString('data-step-name="One"', $this->nextUpHtml($user));
    }

    public function test_unskipping_a_step_reopens_auto_skipped_routine(): void
    {
        $user = User::factory()->create();
        [$routine, $steps] = $this->threeStepRoutine($user);

        foreach ($steps as $step) {
            $this->actingAs($user)->postJson(route('planner.check-items.skip', $step))->assertOk();
        }
        $this->assertNotNull(
            $routine->fresh()->completions()->where('completed_date', now()->toDateString())->first()
        );

        $this->actingAs($user)->postJson(route('planner.check-items.skip', $steps[1]))
            ->assertOk()->assertJson(['ok' => true, 'skipped' => false, 'routine_skipped' => false]);

        $this->assertNull(
            $routine->fresh()->completions()->where('completed_date', now()->toDateString())->first()
        );
    }

    public function test_manual_routine_skip_survives_step_unskip(): void
    {
        $user = User::factory()->create();
        [$routine, $steps] = $this->threeStepRoutine($user);

        // Manual ✗ on the routine first (no reason marker).
        $this->actingAs($user)->postJson(route('planner.routines.skip', $routine))->assertOk();
        // Then fail every step: the manual row must not be overwritten.
        foreach ($steps as $step) {
            $this->actingAs($user)->postJson(route('planner.check-items.skip', $step))->assertOk();
        }
        $record = $routine->fresh()->completions()->where('completed_date', now()->toDateString())->first();
        $this->assertSame('skipped', $record->status);
        $this->assertNull($record->skip_reason);

        // Reopening one step keeps the manual day-close in place.
        $this->actingAs($user)->postJson(route('planner.check-items.skip', $steps[0]))
            ->assertOk()->assertJson(['ok' => true, 'routine_skipped' => true]);
    }

    public function test_partial_skip_renders_badge_and_skip_rest(): void
    {
        $user = User::factory()->create();
        [$routine, $steps] = $this->threeStepRoutine($user);

        $this->actingAs($user)->postJson(route('planner.check-items.skip', $steps[0]))->assertOk();

        $html = $this->dayHtml($user);
        $this->assertStringContainsString('data-skip-count', $html);
        $this->assertStringContainsString('✗1', $html);
        $this->assertStringContainsString('data-skip-rest', $html);
    }

    public function test_completing_a_step_reports_routine_reopened(): void
    {
        $user = User::factory()->create();
        [$routine, $steps] = $this->threeStepRoutine($user);

        $this->actingAs($user)->postJson(route('planner.routines.skip', $routine))->assertOk();
        $this->actingAs($user)->postJson(route('planner.check-items.toggle', $steps[0]))
            ->assertOk()->assertJsonPath('routine_skipped', false);
    }
}
