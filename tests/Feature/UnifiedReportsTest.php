<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineViolation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reports.overview'))->assertRedirect(route('login'));
    }

    public function test_overview_renders_with_all_sections(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('reports.overview'))->assertOk()->getContent();

        foreach (['Reports', 'Tasks done', 'Routine adherence', 'Slips', 'Workouts done', 'Time tracked', 'Avoid habits', 'Workouts', 'Time'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_tasks_section_counts_created_completed_and_overdue(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => 'completed', 'completed_at' => now(),
            'created_at' => now()->subHours(5),
        ]);
        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => 'to_do', 'due_date' => now()->subDay()->toDateString(),
        ]);

        $html = $this->actingAs($user)->get(route('reports.overview', ['range' => 'week']))->assertOk()->getContent();

        $this->assertStringContainsString($project->name, $html);
        $this->assertStringContainsString('Overdue now', $html);
    }

    public function test_routines_and_avoid_sections(): void
    {
        $user = User::factory()->create();

        $build = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Read books']);
        $build->toggleOn(now());

        $avoid = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid', 'title' => 'No smoking']);
        RoutineViolation::create([
            'routine_id' => $avoid->id, 'user_id' => $user->id,
            'occurred_at' => now(), 'occurred_date' => now()->toDateString(),
            'quantity' => 2, 'trigger' => 'coffee',
        ]);

        $html = $this->actingAs($user)->get(route('reports.overview', ['range' => 'week']))->assertOk()->getContent();

        $this->assertStringContainsString('Read books', $html);
        $this->assertStringContainsString('No smoking', $html);
        $this->assertStringContainsString('coffee', $html);
    }

    public function test_workouts_and_time_sections(): void
    {
        $user = User::factory()->create();

        $plan = \App\Models\WorkoutPlan::create(['user_id' => $user->id, 'title' => 'Plan']);
        $day = \App\Models\WorkoutDay::create(['workout_plan_id' => $plan->id, 'weekday' => strtolower(now()->format('l')), 'title' => 'Day', 'type' => 'training']);
        WorkoutSession::create([
            'user_id' => $user->id, 'workout_day_id' => $day->id, 'workout_date' => now()->toDateString(),
            'status' => WorkoutSession::COMPLETED,
            'started_at' => now()->subHour(), 'ended_at' => now(),
        ]);
        TimeEntry::create([
            'user_id' => $user->id, 'status' => TimeEntry::STATUS_STOPPED,
            'started_at' => now()->subHours(2), 'ended_at' => now()->subHour(),
            'duration_seconds' => 3600,
        ]);

        $html = $this->actingAs($user)->get(route('reports.overview', ['range' => 'week']))->assertOk()->getContent();

        $this->assertStringContainsString('1h', $html);
    }

    public function test_custom_range_and_scoping(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Task::factory()->create(['user_id' => $other->id, 'status' => 'completed', 'completed_at' => now()]);
        Task::factory()->create(['user_id' => $user->id, 'status' => 'completed', 'completed_at' => now()->subDays(40)]);

        // Old completion is outside the default week window.
        $week = $this->actingAs($user)->get(route('reports.overview', ['range' => 'week']))->assertOk()->getContent();
        $this->assertStringContainsString('This week', $week);

        // Custom window covering the old completion + other user's task excluded.
        $from = now()->subDays(45)->toDateString();
        $to = now()->toDateString();
        $custom = $this->actingAs($user)->get(route('reports.overview', ['range' => 'custom', 'from' => $from, 'to' => $to]))->assertOk()->getContent();
        $this->assertStringContainsString('value="'.$from.'"', $custom);
        $this->assertStringContainsString('value="'.$to.'"', $custom);
    }
}
