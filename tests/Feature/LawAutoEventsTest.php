<?php

namespace Tests\Feature;

use App\Models\Law;
use App\Models\LawEvent;
use App\Models\Project;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawAutoEventsTest extends TestCase
{
    use RefreshDatabase;

    private function makeLaw(User $user, array $overrides = []): Law
    {
        return $user->laws()->create(array_merge([
            'title' => 'Test law',
            'coefficient' => 4,
            'starts_on' => today()->toDateString(),
            'status' => Law::STATUS_ACTIVE,
        ], $overrides));
    }

    private function linkTask(Law $law, Task $task, int $effect = 1): void
    {
        $law->links()->create([
            'linkable_type' => Task::class,
            'linkable_id' => $task->id,
            'effect' => $effect,
        ]);
    }

    private function linkRoutine(Law $law, Routine $routine, int $effect = 1): void
    {
        $law->links()->create([
            'linkable_type' => Routine::class,
            'linkable_id' => $routine->id,
            'effect' => $effect,
        ]);
    }

    // ── Tasks ──────────────────────────────────────────────────────

    public function test_completing_linked_task_scores_and_uncompleting_retracts(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $task = Task::factory()->create(['user_id' => $user->id]);
        $this->linkTask($law, $task, 1);

        $this->actingAs($user)->post(route('planner.tasks.toggle', $task))->assertOk();
        $this->assertDatabaseHas('law_events', [
            'law_id' => $law->id, 'direction' => 1, 'xp_delta' => 4,
            'source' => 'auto', 'linkable_type' => Task::class, 'linkable_id' => $task->id,
        ]);

        $this->actingAs($user)->post(route('planner.tasks.toggle', $task))->assertOk();
        $this->assertEquals(0, LawEvent::count());
    }

    public function test_against_link_scores_negative_on_completion(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $task = Task::factory()->create(['user_id' => $user->id]);
        $this->linkTask($law, $task, -1);

        $this->actingAs($user)->post(route('planner.tasks.toggle', $task))->assertOk();

        $this->assertEquals(-4, LawEvent::first()->xp_delta);
    }

    public function test_failing_task_scores_opposite_and_unfail_retracts(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $task = Task::factory()->create(['user_id' => $user->id]);
        $this->linkTask($law, $task, 1);

        $this->actingAs($user)->post(route('planner.tasks.fail', $task), ['note' => 'nope'])->assertOk();
        $this->assertEquals(-4, LawEvent::first()->xp_delta);

        $this->actingAs($user)->post(route('planner.tasks.unfail', $task))->assertOk();
        $this->assertEquals(0, LawEvent::count());
    }

    public function test_kanban_and_form_and_bulk_status_changes_score(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $a = Task::factory()->create(['user_id' => $user->id]);
        $b = Task::factory()->create(['user_id' => $user->id]);
        $c = Task::factory()->create(['user_id' => $user->id]);
        $this->linkTask($law, $a, 1);
        $this->linkTask($law, $b, 1);
        $this->linkTask($law, $c, 1);

        // Kanban drag (unnamed route).
        $this->actingAs($user)->post("/tasks/{$a->id}/update-status", ['status' => 'completed'])->assertOk();
        $this->assertEquals(1, LawEvent::where('linkable_id', $a->id)->count());

        // Edit form.
        $this->actingAs($user)->put(route('tasks.update', $b), [
            'title' => $b->title, 'priority' => 'medium', 'status' => 'completed',
        ])->assertRedirect();
        $this->assertEquals(1, LawEvent::where('linkable_id', $b->id)->count());

        // Bulk.
        $this->actingAs($user)->post(route('tasks.bulk-update'), [
            'ids' => [$b->id, $c->id], 'status' => 'to_do',
        ])->assertOk();
        $this->assertEquals(0, LawEvent::where('linkable_id', $b->id)->count());
        $this->assertEquals(0, LawEvent::where('linkable_id', $c->id)->count());
    }

    public function test_project_inheritance_scores_and_exclusion_blocks(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);
        $law->links()->create(['linkable_type' => Project::class, 'linkable_id' => $project->id, 'effect' => 1]);

        $this->actingAs($user)->post(route('planner.tasks.toggle', $task))->assertOk();
        $this->assertEquals(4, LawEvent::first()->xp_delta);

        // Opt out → retract + no future score.
        $task->update(['status' => 'to_do', 'completed_at' => null]);
        $law->links()->create([
            'linkable_type' => Task::class, 'linkable_id' => $task->id,
            'effect' => 1, 'is_excluded' => true,
        ]);
        $this->actingAs($user)->post(route('planner.tasks.toggle', $task))->assertOk();
        $this->assertEquals(0, LawEvent::count());
    }

    public function test_paused_and_expired_laws_never_score(): void
    {
        $user = User::factory()->create();
        $paused = $this->makeLaw($user, ['title' => 'Paused', 'status' => Law::STATUS_PAUSED]);
        $task = Task::factory()->create(['user_id' => $user->id]);
        $this->linkTask($paused, $task, 1);

        $this->actingAs($user)->post(route('planner.tasks.toggle', $task))->assertOk();
        $this->assertEquals(0, LawEvent::count());
    }

    public function test_uncompleting_child_reopens_parent_scores(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $parent = Task::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'completed_at' => now()]);
        $child = Task::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'status' => 'completed', 'completed_at' => now()]);
        $this->linkTask($law, $parent, 1);
        app(\App\Services\LawEventService::class)->syncTaskTree($parent);
        $this->assertEquals(1, LawEvent::count());

        $this->actingAs($user)->post(route('planner.tasks.toggle', $child))->assertOk();

        $this->assertSame('to_do', $parent->fresh()->status);
        $this->assertEquals(0, LawEvent::count());
    }

    public function test_deleting_task_removes_its_auto_events(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $task = Task::factory()->create(['user_id' => $user->id]);
        $this->linkTask($law, $task, 1);

        $this->actingAs($user)->post(route('planner.tasks.toggle', $task))->assertOk();
        $this->assertEquals(1, LawEvent::count());

        $this->actingAs($user)->delete(route('tasks.destroy', $task))->assertRedirect();
        $this->assertEquals(0, LawEvent::count());
    }

    // ── Routines ───────────────────────────────────────────────────

    public function test_build_routine_done_skip_and_postpone(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $this->linkRoutine($law, $routine, 1);

        $this->actingAs($user)->post(route('planner.routines.toggle', $routine))->assertOk();
        $this->assertEquals(4, LawEvent::first()->xp_delta);

        // Uncheck retracts.
        $this->actingAs($user)->post(route('planner.routines.toggle', $routine))->assertOk();
        $this->assertEquals(0, LawEvent::count());

        // Skip scores the opposite.
        $this->actingAs($user)->post(route('planner.routines.skip', $routine), ['reason' => 'no_time'])->assertOk();
        $this->assertEquals(-4, LawEvent::first()->xp_delta);
    }

    public function test_postpone_does_not_score(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'every_n_days', 'every_n_days' => 2]);
        $this->linkRoutine($law, $routine, 1);

        $this->actingAs($user)->post(route('planner.routines.postpone', $routine))->assertOk();
        $this->assertEquals(0, LawEvent::count());
    }

    public function test_avoid_slip_scores_link_effect_once(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $routine = Routine::factory()->create(['user_id' => $user->id, 'behavior_type' => 'avoid']);
        $this->linkRoutine($law, $routine, -1);

        $this->actingAs($user)->post(route('planner.routines.slip', $routine), ['quantity' => 2])->assertOk();
        $this->assertEquals(1, LawEvent::count());
        $this->assertEquals(-4, LawEvent::first()->xp_delta);
        $this->assertSame(\App\Models\RoutineViolation::class, LawEvent::first()->linkable_type);

        // Plain-mode dedupe returns the existing violation → no double score.
        $this->actingAs($user)->post(route('planner.routines.slip', $routine))->assertOk();
        $this->assertEquals(1, LawEvent::count());

        $violation = \App\Models\RoutineViolation::first();
        $this->actingAs($user)->delete(route('planner.violations.destroy', $violation))->assertOk();
        $this->assertEquals(0, LawEvent::count());
    }

    // ── Surfaces ───────────────────────────────────────────────────

    public function test_my_day_dashboard_and_reports_show_law_score(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user, ['title' => 'Scoreboard law']);
        $task = Task::factory()->create(['user_id' => $user->id]);
        $this->linkTask($law, $task, 1);

        $this->actingAs($user)->post(route('planner.tasks.toggle', $task))->assertOk();

        $this->actingAs($user)->get(route('planner.index'))->assertOk()->assertSee('Open Laws');
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Scoreboard law');
        $this->actingAs($user)->get(route('reports.overview', ['range' => 'week']))->assertOk()->assertSee('Law XP');
    }
}
