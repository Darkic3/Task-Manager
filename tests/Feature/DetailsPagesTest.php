<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetailsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_details_page_renders_minimal_layout(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Read chapter one',
            'status' => 'in_progress',
            'priority' => 'high',
        ]);
        $child = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'parent_id' => $task->id,
            'title' => 'Summarize section 1.1',
            'status' => 'completed',
        ]);
        ChecklistItem::create(['task_id' => $task->id, 'name' => 'Take notes', 'completed' => false]);

        $response = $this->actingAs($user)->get(route('tasks.show', $task));

        $response->assertOk();
        $response->assertSee('Read chapter one');
        // Status chip + priority + subtasks + checklist affordances
        $response->assertSee('ts-chip in_progress', false);
        $response->assertSee('Subtasks (1/1)', false);
        $response->assertSee('Summarize section 1.1');
        $response->assertSee('Take notes');
        $response->assertSee('openStatusModal()', false);
        // Minimal look: no gradient header, no sidebar panel
        $response->assertDontSee('ts-header', false);
        $response->assertDontSee('.ts-panel {', false);
    }

    public function test_task_details_page_without_optional_relations(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'description' => null,
        ]);

        $response = $this->actingAs($user)->get(route('tasks.show', $task));

        $response->assertOk();
        $response->assertDontSee('Subtasks (', false);
    }

    public function test_project_details_page_renders_minimal_layout(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id, 'name' => 'Study Project', 'status' => 'in_progress',
        ]);
        $sub = Project::factory()->create([
            'user_id' => $user->id, 'name' => 'Chapter One', 'parent_id' => $project->id,
            'status' => 'in_progress',
        ]);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $sub->id, 'status' => 'completed']);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $sub->id, 'status' => 'to_do']);
        $project->users()->attach($user->id);

        $response = $this->actingAs($user)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('Study Project');
        $response->assertSee('Chapter One');
        $response->assertSee('1/2', false);
        $response->assertSee($user->email);
        $response->assertSee('addMemberModal', false);
        $response->assertSee(route('projects.tasks.index', $project), false);
        // Minimal look: no gradient header, no sidebar panel
        $response->assertDontSee('content-header', false);
        $response->assertDontSee('cu-left-panel', false);
        $response->assertDontSee('cu-panel-avatar', false);
    }
}
