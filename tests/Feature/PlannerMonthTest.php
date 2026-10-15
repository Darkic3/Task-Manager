<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerMonthTest extends TestCase
{
    use RefreshDatabase;

    public function test_month_view_renders_compact_grid_with_tasks_and_routines(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Alpha']);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'October quest', 'status' => 'to_do', 'due_date' => '2026-10-15']);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'Second quest', 'status' => 'to_do', 'due_date' => '2026-10-15']);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'Finished quest', 'status' => 'completed', 'due_date' => '2026-10-15']);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'November quest', 'status' => 'to_do', 'due_date' => '2026-11-02']);

        $routine = Routine::factory()->create(['user_id' => $user->id, 'title' => 'Daily run', 'frequency' => 'daily', 'created_at' => '2026-09-01 08:00:00']);
        RoutineCompletion::create([
            'user_id' => $user->id, 'routine_id' => $routine->id,
            'completed_date' => '2026-10-15', 'completed_at' => now(),
            'status' => RoutineCompletion::STATUS_DONE,
        ]);

        $html = $this->actingAs($user)
            ->get(route('planner.index', ['view' => 'month', 'date' => '2026-10-15']))
            ->assertOk()
            ->getContent();

        // Grid shell, weekday header, month toggle active.
        $this->assertStringContainsString('pm-grid', $html);
        $this->assertStringContainsString('pm-weekdays', $html);
        $this->assertStringContainsString('pl-toggle-btn active', $html);
        $this->assertStringContainsString('My Month', $html);
        $this->assertStringContainsString('October 2026', $html);

        // Task aggregation on the day cell.
        $this->assertStringContainsString('October quest', $html);
        $this->assertStringContainsString('Second quest', $html);
        $this->assertStringContainsString('November quest', $html);

        // Day cells link through to the day view; month nav keeps view=month.
        $this->assertStringContainsString('view=day', $html);
        $this->assertStringContainsString('view=month', $html);

        // Routine occurrence + completion is counted (1/1 on Oct 15).
        $this->assertStringContainsString('1/1', $html);
    }

    public function test_month_view_counts_full_month_routine_occurrences(): void
    {
        $user = User::factory()->create();
        Routine::factory()->create(['user_id' => $user->id, 'title' => 'Daily run', 'frequency' => 'daily', 'created_at' => '2026-09-01 08:00:00']);

        $html = $this->actingAs($user)
            ->get(route('planner.index', ['view' => 'month', 'date' => '2026-10-15']))
            ->assertOk()
            ->getContent();

        // 31 daily occurrences inside October (done/total renders as 0<span>/31</span>).
        $this->assertStringContainsString('/31<', $html);
    }

    public function test_unknown_view_falls_back_to_day(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('planner.index', ['view' => 'year', 'date' => '2026-10-15']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('My Day', $html);
        $this->assertStringNotContainsString('<div class="pm-grid"', $html);
        $this->assertStringNotContainsString('<div class="pm-days"', $html);
    }
}
