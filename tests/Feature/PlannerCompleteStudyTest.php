<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerCompleteStudyTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_study_sets_pages_completes_and_stops_timer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $project = Project::factory()->create([
            'user_id' => $user->id, 'daily_goal_value' => 5, 'daily_goal_unit' => 'pages',
        ]);
        $task = Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id,
            'status' => 'in_progress', 'weight' => 3,
        ]);
        $entry = TimeEntry::create([
            'user_id' => $user->id, 'project_id' => $project->id, 'task_id' => $task->id,
            'status' => TimeEntry::STATUS_RUNNING, 'started_at' => now()->subMinutes(25),
        ]);

        $response = $this->postJson(route('planner.tasks.complete-study', $task), ['pages' => 12]);

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('completed', true);
        $response->assertJsonPath('pages', 12);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed', 'weight' => 12]);
        $this->assertSame(TimeEntry::STATUS_STOPPED, $entry->fresh()->status);
        $this->assertGreaterThan(0, (int) $response->json('stopped_seconds'));

        // Goal payload reflects the fresh project state (12/5 pages → met).
        $this->assertTrue((bool) $response->json('goal.met'));
        $this->assertSame($project->id, $response->json('goal.project_id'));
    }

    public function test_complete_study_without_pages_keeps_weight(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->create([
            'user_id' => $user->id, 'status' => 'to_do', 'weight' => 7,
        ]);

        $response = $this->postJson(route('planner.tasks.complete-study', $task), []);

        $response->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed', 'weight' => 7]);
        $this->assertNull($response->json('goal'));
    }

    public function test_complete_study_validates_and_scopes_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $task = Task::factory()->create(['user_id' => $user->id, 'status' => 'to_do']);
        $foreign = Task::factory()->create(['user_id' => $other->id, 'status' => 'to_do']);

        $this->postJson(route('planner.tasks.complete-study', $task), ['pages' => 500])
            ->assertUnprocessable();

        $this->postJson(route('planner.tasks.complete-study', $foreign), [])
            ->assertForbidden();
    }

    public function test_planner_row_has_study_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $html = $this->get(route('planner.index'))->assertOk()->getContent();

        $this->assertStringContainsString('plCompleteStudy', $html);
    }
}
