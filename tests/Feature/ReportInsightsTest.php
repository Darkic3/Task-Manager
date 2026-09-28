<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Routine;
use App\Models\RoutineViolation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_state_shows_neutral_insight(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('reports.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('Not enough data yet', $html);
    }

    public function test_slip_peak_window_overdue_and_spotlight(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Client X']);

        $avoid = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid']);
        foreach (['19:30', '20:15'] as $t) {
            RoutineViolation::create([
                'routine_id' => $avoid->id, 'user_id' => $user->id,
                'occurred_at' => now()->format('Y-m-d').' '.$t,
                'occurred_date' => now()->toDateString(), 'quantity' => 1,
            ]);
        }

        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => 'to_do', 'due_date' => now()->subDay()->toDateString(),
        ]);
        TimeEntry::create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => TimeEntry::STATUS_STOPPED,
            'started_at' => now()->subHours(3), 'ended_at' => now()->subHour(),
            'duration_seconds' => 7200,
        ]);

        $html = $this->actingAs($user)->get(route('reports.overview', ['range' => 'week']))->assertOk()->getContent();

        $this->assertStringContainsString('18:00–21:00', $html);
        $this->assertStringContainsString('overdue task', $html);
        $this->assertStringContainsString('Spotlight: Client X', $html);
    }

    public function test_trend_and_streak_insights(): void
    {
        $user = User::factory()->create();

        // Completed now (in week) + nothing before → positive trend.
        Task::factory()->create([
            'user_id' => $user->id, 'status' => 'completed',
            'completed_at' => now(), 'created_at' => now()->subDay(),
        ]);

        // Keep the avoid routine clean for a streak (created 5 days ago).
        Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily',
            'behavior_type' => 'avoid', 'created_at' => now()->subDays(5),
        ]);

        $html = $this->actingAs($user)->get(route('reports.overview', ['range' => 'week']))->assertOk()->getContent();

        $this->assertStringContainsString('Completion rate up', $html);
        $this->assertStringContainsString('clean streak', $html);
    }
}
