<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDailyGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_update_stores_daily_goal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->put(route('projects.update', $project), [
            'name' => $project->name,
            'status' => 'in_progress',
            'daily_goal_value' => 3,
            'daily_goal_unit' => 'pages',
        ]);

        $response->assertRedirect(route('projects.index'));
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'daily_goal_value' => 3,
            'daily_goal_unit' => 'pages',
        ]);
    }

    public function test_empty_daily_goal_disables_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'daily_goal_value' => 5,
            'daily_goal_unit' => 'tasks',
        ]);

        $this->put(route('projects.update', $project), [
            'name' => $project->name,
            'status' => 'in_progress',
            'daily_goal_value' => null,
            'daily_goal_unit' => 'tasks',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'daily_goal_value' => null,
            'daily_goal_unit' => null,
        ]);
    }

    public function test_daily_goal_validation_rejects_bad_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->put(route('projects.update', $project), [
            'name' => $project->name,
            'status' => 'in_progress',
            'daily_goal_value' => 0,
            'daily_goal_unit' => 'tasks',
        ])->assertSessionHasErrors('daily_goal_value');

        $this->put(route('projects.update', $project), [
            'name' => $project->name,
            'status' => 'in_progress',
            'daily_goal_value' => 2,
            'daily_goal_unit' => 'chapters',
        ])->assertSessionHasErrors('daily_goal_unit');
    }

    public function test_daily_goal_progress_counts_tasks_in_subtree(): void
    {
        $user = User::factory()->create();

        $parent = Project::factory()->create(['user_id' => $user->id, 'daily_goal_value' => 3, 'daily_goal_unit' => 'tasks']);
        $child = Project::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id]);

        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $child->id,
            'status' => 'completed', 'completed_at' => now(),
        ]);
        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $parent->id,
            'status' => 'completed', 'completed_at' => now(),
        ]);
        // Yesterday's completion must not count.
        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $parent->id,
            'status' => 'completed', 'completed_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);

        $progress = $parent->fresh()->dailyGoalProgress();

        $this->assertSame(2, $progress['current']);
        $this->assertSame(3, $progress['target']);
        $this->assertSame('tasks', $progress['unit']);
        $this->assertSame(1, $progress['remaining']);
        $this->assertFalse($progress['met']);
    }

    public function test_daily_goal_progress_pages_and_minutes(): void
    {
        $user = User::factory()->create();

        $pagesProject = Project::factory()->create(['user_id' => $user->id, 'daily_goal_value' => 20, 'daily_goal_unit' => 'pages']);
        Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $pagesProject->id,
            'status' => 'completed', 'completed_at' => now(), 'weight' => 12,
        ]);

        $pages = $pagesProject->fresh()->dailyGoalProgress();
        $this->assertEquals(12, $pages['current']);
        $this->assertEquals(60.0, $pages['percent']);

        $timeProject = Project::factory()->create(['user_id' => $user->id, 'daily_goal_value' => 60, 'daily_goal_unit' => 'minutes']);
        TimeEntry::create([
            'user_id' => $user->id,
            'project_id' => $timeProject->id,
            'started_at' => now()->subMinutes(90),
            'ended_at' => now()->subMinutes(60),
            'duration_seconds' => 1800,
            'status' => TimeEntry::STATUS_STOPPED,
        ]);

        $minutes = $timeProject->fresh()->dailyGoalProgress();
        $this->assertSame(30, $minutes['current']);
        $this->assertTrue($minutes['remaining'] === 30);
    }

    public function test_project_show_renders_daily_goal_card(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'user_id' => $user->id, 'daily_goal_value' => 2, 'daily_goal_unit' => 'tasks',
        ]);

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Daily goal', false);
    }
}
