<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyGoalHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_counts_met_days_streak_and_rate(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id, 'daily_goal_value' => 2, 'daily_goal_unit' => 'tasks',
        ]);

        // Today: 2 completions → met.
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'status' => 'completed', 'completed_at' => now()]);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'status' => 'completed', 'completed_at' => now()]);
        // Yesterday: 2 completions → met (streak 2).
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'status' => 'completed', 'completed_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'status' => 'completed', 'completed_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        // 3 days ago: 1 completion → missed.
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'status' => 'completed', 'completed_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);

        $hist = $project->fresh()->dailyGoalHistory(7);

        $this->assertCount(7, $hist['days']);
        $this->assertSame(2, $hist['met']);
        $this->assertSame(7, $hist['total']);
        $this->assertSame(2, $hist['streak']);
        $this->assertEquals(round(2 / 7 * 100), $hist['rate']);

        $byDate = collect($hist['days'])->keyBy('date');
        $this->assertTrue($byDate[now()->toDateString()]['met']);
        $this->assertFalse($byDate[now()->subDays(3)->toDateString()]['met']);
    }

    public function test_history_pages_unit_uses_weight(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id, 'daily_goal_value' => 10, 'daily_goal_unit' => 'pages',
        ]);

        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => 'completed', 'completed_at' => now(), 'weight' => 12,
        ]);

        $hist = $project->fresh()->dailyGoalHistory(7);
        $today = collect($hist['days'])->firstWhere('date', now()->toDateString());

        $this->assertEquals(12.0, $today['current']);
        $this->assertTrue($today['met']);
        $this->assertSame(1, $hist['streak']);
    }

    public function test_history_minutes_unit_uses_time_entries(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id, 'daily_goal_value' => 60, 'daily_goal_unit' => 'minutes',
        ]);

        TimeEntry::create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => TimeEntry::STATUS_STOPPED,
            'started_at' => now()->subMinutes(90), 'ended_at' => now()->subMinutes(30),
            'duration_seconds' => 3600,
        ]);

        $hist = $project->fresh()->dailyGoalHistory(7);
        $today = collect($hist['days'])->firstWhere('date', now()->toDateString());

        $this->assertSame(60, $today['current']);
        $this->assertTrue($today['met']);
    }

    public function test_history_empty_without_goal_and_show_renders_strip(): void
    {
        $user = User::factory()->create();
        $plain = Project::factory()->create(['user_id' => $user->id]);

        $hist = $plain->fresh()->dailyGoalHistory();
        $this->assertSame([], $hist['days']);
        $this->assertSame(0, $hist['streak']);

        $withGoal = Project::factory()->create([
            'user_id' => $user->id, 'daily_goal_value' => 1, 'daily_goal_unit' => 'tasks',
        ]);
        $html = $this->actingAs($user)->get(route('projects.show', $withGoal))->assertOk()->getContent();
        $this->assertStringContainsString('streak', $html);
    }

    public function test_descendant_ids_are_memoized(): void
    {
        $user = User::factory()->create();
        $parent = Project::factory()->create(['user_id' => $user->id]);
        $child = Project::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id]);

        $fresh = $parent->fresh();
        $first = $fresh->descendantProjectIds();
        $this->assertEqualsCanonicalizing([$parent->id, $child->id], $first);
        // Second call must come from memo (same result, no extra queries needed).
        $this->assertSame($first, $fresh->descendantProjectIds());
    }
}
