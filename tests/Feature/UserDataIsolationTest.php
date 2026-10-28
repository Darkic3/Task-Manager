<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_read_edit_update_or_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $task = Task::factory()->create(['user_id' => $owner->id, 'project_id' => $project->id]);

        $this->actingAs($intruder)->get(route('tasks.show', $task))->assertForbidden();
        $this->get(route('tasks.edit', $task))->assertForbidden();
        $this->put(route('tasks.update', $task), [
            'title' => 'Hijacked',
            'priority' => 'high',
            'status' => 'to_do',
        ])->assertForbidden();
        $this->delete(route('tasks.destroy', $task))->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => $task->title, 'user_id' => $owner->id]);
    }

    public function test_a_user_cannot_read_or_mutate_another_users_project(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('projects.show', $project))->assertForbidden();
        $this->get(route('projects.edit', $project))->assertForbidden();
        $this->put(route('projects.update', $project), [
            'name' => 'Hijacked',
            'status' => 'in_progress',
        ])->assertForbidden();
        $this->delete(route('projects.destroy', $project))->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'user_id' => $owner->id]);
    }

    public function test_child_checklist_routes_are_scoped_through_their_parent_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $owner->id]);
        $item = ChecklistItem::create(['task_id' => $task->id, 'name' => 'Private item', 'completed' => false]);

        $this->actingAs($intruder)->get(route('checklist-items.update-status', $item))->assertForbidden();
    }
}
