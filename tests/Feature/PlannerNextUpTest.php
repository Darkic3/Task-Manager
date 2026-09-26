<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineChecklistItem;
use App\Models\RoutineCompletion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerNextUpTest extends TestCase
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

    private function nextUpHtml(User $user, ?string $date = null): string
    {
        $response = $this->actingAs($user)->getJson(
            route('planner.next-up', ['date' => $date ?? now()->toDateString()])
        );

        $response->assertOk();

        return $response->json('html');
    }

    public function test_picks_highest_priority_task(): void
    {
        $user = User::factory()->create();
        $this->task($user, 'Low thing', ['priority' => 'low']);
        $this->task($user, 'Urgent thing', ['priority' => 'high']);

        $html = $this->nextUpHtml($user);

        $this->assertStringContainsString('Urgent thing', $html);
        $this->assertStringNotContainsString('Low thing', $html);
        $this->assertStringContainsString('data-next-type="task"', $html);
    }

    public function test_prefers_task_over_routine(): void
    {
        $user = User::factory()->create();
        $this->task($user, 'Real task', ['priority' => 'medium']);
        Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'daily',
            'title' => 'Some routine',
        ]);

        $html = $this->nextUpHtml($user);

        $this->assertStringContainsString('Real task', $html);
        $this->assertStringNotContainsString('Some routine', $html);
    }

    public function test_falls_back_to_routine_when_no_tasks(): void
    {
        $user = User::factory()->create();
        Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'daily',
            'title' => 'Evening walk',
        ]);

        $html = $this->nextUpHtml($user);

        $this->assertStringContainsString('Evening walk', $html);
        $this->assertStringContainsString('data-next-type="routine"', $html);
    }

    public function test_advances_to_next_task_after_completion(): void
    {
        $user = User::factory()->create();
        $first = $this->task($user, 'First up', ['priority' => 'high']);
        $this->task($user, 'Second up', ['priority' => 'medium']);

        $this->assertStringContainsString('First up', $this->nextUpHtml($user));

        $this->actingAs($user)->postJson(route('planner.tasks.toggle', $first))->assertOk();

        $html = $this->nextUpHtml($user);
        $this->assertStringContainsString('Second up', $html);
        $this->assertStringNotContainsString('First up', $html);
    }

    public function test_hides_when_nothing_left(): void
    {
        $user = User::factory()->create();

        $html = $this->nextUpHtml($user);

        $this->assertStringContainsString('id="plNextUp"', $html);
        $this->assertStringNotContainsString('pl-next-card', $html);
    }

    public function test_scoped_to_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->task($other, 'Other secret', ['priority' => 'high']);

        $html = $this->nextUpHtml($user);

        $this->assertStringNotContainsString('Other secret', $html);
        $this->assertStringNotContainsString('pl-next-card', $html);
    }

    /* ── Multi-step guidance ── */

    public function test_showcase_routine_points_at_first_open_step(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Cobra Pose',
        ]);
        $warmup = RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Warmup', 'sort_order' => 0]);
        RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Hold pose', 'sort_order' => 1]);
        $warmup->toggleOn(now());

        $html = $this->nextUpHtml($user);

        $this->assertStringContainsString('Cobra Pose', $html);
        $this->assertStringContainsString('data-next-type="routine"', $html);
        $this->assertStringContainsString('Hold pose', $html);   // first open step guided
        $this->assertStringContainsString('1/2', $html);          // progress shown, not "done"
        $this->assertStringContainsString('Complete step', $html);
    }

    public function test_tracked_final_step_offers_guided_single_log_call(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Cobra Pose',
            'tracking_mode' => 'sets',
        ]);
        $first = RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Step A', 'sort_order' => 0, 'target_sets' => 1]);
        $second = RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Step B', 'sort_order' => 1, 'target_sets' => 1]);
        $third = RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $routine->id, 'name' => 'Step C', 'sort_order' => 2, 'target_sets' => 1]);
        $today = now()->toDateString();
        // In sets mode the only way to be 2/3 is with logged numbers (auto-ticked).
        $this->actingAs($user)->postJson(
            route('planner.routines.log', $routine) . '?date=' . $today,
            ['item_id' => $first->id, 'sets' => ['1' => 10]]
        )->assertOk();
        $this->actingAs($user)->postJson(
            route('planner.routines.log', $routine) . '?date=' . $today,
            ['item_id' => $second->id, 'sets' => ['1' => 12]]
        )->assertOk();

        $html = $this->nextUpHtml($user);

        // Card contract: guided step inputs + per-step toggle URL, one call does the tick.
        $this->assertStringContainsString('2/3', $html);
        $this->assertStringContainsString('Step C', $html);
        $this->assertStringContainsString('data-logsets="1"', $html);
        $this->assertStringContainsString(route('planner.routines.log', $routine), $html);
        $this->assertStringContainsString('data-set="1"', $html);
        $this->assertStringContainsString(route('planner.check-items.toggle', $third->id), $html);

        // One log call ticks the final step AND completes the routine — no second toggle needed.
        $this->actingAs($user)->postJson(
            route('planner.routines.log', $routine) . '?date=' . $today,
            ['item_id' => $third->id, 'sets' => ['1' => 15]]
        )->assertOk()->assertJsonPath('routine_completed', true);

        $this->assertTrue($third->completedOn(now()));
        $this->assertTrue($routine->fresh()->completedOn(now()));

        // Completed routines must no longer be offered.
        $html = $this->nextUpHtml($user);
        $this->assertStringNotContainsString('Cobra Pose', $html);
    }

    public function test_routine_card_floats_to_active_step_schedule(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Cobra Pose',
            'time_period' => 'morning', // routine-level period should be ignored when steps have schedules
        ]);
        $morning = RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $routine->id, 'name' => '15 - Morning', 'sort_order' => 0, 'time_period' => 'morning']);
        $afternoon = RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $routine->id, 'name' => '15 - Afternoon', 'sort_order' => 1, 'time_period' => 'afternoon']);
        $night = RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $routine->id, 'name' => '15 - Night', 'sort_order' => 2, 'time_period' => 'night']);

        // Initial: active step is Morning, even though routine-level is also morning.
        $html = $this->nextUpHtml($user);
        $this->assertStringContainsString('Morning', $html);

        // Finish morning step → active step moves to Afternoon.
        $morning->toggleOn(now());
        $html = $this->nextUpHtml($user);
        $this->assertStringContainsString('Afternoon', $html);
        $this->assertStringNotContainsString('data-next-step-id="'.$morning->id.'"', $html);

        // Finish afternoon → active step becomes Night.
        $afternoon->toggleOn(now());
        $html = $this->nextUpHtml($user);
        $this->assertStringContainsString('Night', $html);

        // Finish night (routine auto-completes) → badge stays on the last step's time (Night).
        $night->toggleOn(now());
        $html = $this->nextUpHtml($user);
        $this->assertStringContainsString('Night', $html);
    }

    public function test_todays_routines_sort_by_active_step_schedule(): void
    {
        $user = User::factory()->create();
        $nightRoutine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Night routine']);
        RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $nightRoutine->id, 'name' => 'Step', 'sort_order' => 0, 'time_period' => 'night']);

        $exactRoutine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Exact routine']);
        RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $exactRoutine->id, 'name' => 'Step', 'sort_order' => 0, 'scheduled_time' => '07:30']);

        $morningRoutine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Morning routine']);
        RoutineChecklistItem::create(['user_id' => $user->id, 'routine_id' => $morningRoutine->id, 'name' => 'Step', 'sort_order' => 0, 'time_period' => 'morning']);

        $response = $this->actingAs($user)->get('/planner?date='.now()->toDateString());
        $response->assertOk();

        $response->assertSeeInOrder(['Exact routine', 'Morning routine', 'Night routine']);
    }

    public function test_task_with_open_steps_shows_progress_and_next_step(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'Report', ['priority' => 'high']);
        $task->checklistItems()->create(['name' => 'Draft intro', 'completed' => true]);
        $open = $task->checklistItems()->create(['name' => 'Add numbers', 'completed' => false]);

        $html = $this->nextUpHtml($user);

        $this->assertStringContainsString('Add numbers', $html);
        $this->assertStringContainsString('1/2', $html);
        $this->assertStringContainsString(route('planner.task-items.toggle', $open->id), $html);
        $this->assertStringContainsString('Complete step', $html);
    }

    public function test_task_step_toggle_ticks_single_step_not_task(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'Report');
        $task->checklistItems()->create(['name' => 'Draft intro', 'completed' => true]);
        $open = $task->checklistItems()->create(['name' => 'Add numbers', 'completed' => false]);

        $this->actingAs($user)->postJson(route('planner.task-items.toggle', $open))
            ->assertOk()
            ->assertJsonPath('completed', true)
            ->assertJsonPath('task_completed', true)
            ->assertJsonPath('steps_done', 2)
            ->assertJsonPath('steps_total', 2);

        // With another step still open, the task itself must stay open.
        $task2 = $this->task($user, 'Emails');
        $task2->checklistItems()->create(['name' => 'Collect', 'completed' => true]);
        $open2 = $task2->checklistItems()->create(['name' => 'Send', 'completed' => false]);
        $task2->checklistItems()->create(['name' => 'CC boss', 'completed' => false]);

        $this->actingAs($user)->postJson(route('planner.task-items.toggle', $open2))
            ->assertOk()
            ->assertJsonPath('task_completed', false)
            ->assertJsonPath('steps_done', 2)
            ->assertJsonPath('steps_total', 3);

        $task2->refresh();
        $this->assertSame('to_do', $task2->status);
    }

    public function test_task_auto_completes_when_last_step_ticked(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, 'Report');
        $one = $task->checklistItems()->create(['name' => 'One', 'completed' => true]);
        $two = $task->checklistItems()->create(['name' => 'Two', 'completed' => false]);

        $this->actingAs($user)->postJson(route('planner.task-items.toggle', $two))
            ->assertOk()
            ->assertJsonPath('task_completed', true);

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertTrue((bool) $one->fresh()->completed);
    }

    public function test_foreign_task_step_rejected(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->create();
        $task = $this->task($other, 'Secret');
        $item = $task->checklistItems()->create(['name' => 'Step', 'completed' => false]);

        $this->actingAs($user)->postJson(route('planner.task-items.toggle', $item))->assertForbidden();
        $this->assertFalse((bool) $item->fresh()->completed);
    }
}
