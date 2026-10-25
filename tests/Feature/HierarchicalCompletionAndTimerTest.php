<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchicalCompletionAndTimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_task_cannot_be_completed_until_all_subtasks_are_completed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $parent = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'parent_id' => $parent->id]);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'parent_id' => $parent->id]);

        $this->actingAs($user)->postJson("/tasks/{$parent->id}/update-status", ['status' => 'completed'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false);

        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'status' => 'to_do']);
    }

    public function test_completed_parent_is_reopened_when_a_subtask_is_reopened(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $parent = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'status' => 'completed']);
        $child = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'parent_id' => $parent->id,
            'status' => 'completed',
        ]);

        $this->actingAs($user)->postJson("/tasks/{$child->id}/update-status", ['status' => 'to_do'])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'status' => 'to_do', 'completed_at' => null]);
    }

    public function test_stopping_a_timer_only_records_time_and_outcome_can_complete_selected_child(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $parent = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'Lesson']);
        $donePart = Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id, 'parent_id' => $parent->id, 'title' => 'Completed section',
        ]);
        $unfinishedPart = Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id, 'parent_id' => $parent->id, 'title' => 'Remaining section',
        ]);

        $entryId = $this->actingAs($user)->postJson(route('time.start'), ['task_id' => $parent->id])
            ->assertCreated()->json('active.id');

        $stopped = $this->actingAs($user)->postJson(route('time.stop', $entryId))
            ->assertOk()
            ->assertJsonPath('entry.task.id', $parent->id)
            ->assertJsonPath('outcome.tasks.0.id', $donePart->id);

        $this->assertSame('to_do', $parent->fresh()->status, 'Stopping must not complete the task.');
        $this->assertSame(TimeEntry::STATUS_STOPPED, TimeEntry::findOrFail($entryId)->status);

        $this->actingAs($user)->postJson(route('time.outcome', $entryId), [
            'completed_task_ids' => [$donePart->id],
            'completed_project_ids' => [],
            'complete_root' => false,
            'target_type' => 'task',
            'target_id' => $donePart->id,
        ])->assertOk();

        $this->assertSame('completed', $donePart->fresh()->status);
        $this->assertSame('to_do', $unfinishedPart->fresh()->status);
        $this->assertSame('to_do', $parent->fresh()->status);
        $this->assertSame($donePart->id, TimeEntry::findOrFail($entryId)->task_id);

        $this->actingAs($user)->postJson("/tasks/{$unfinishedPart->id}/update-status", ['status' => 'completed'])->assertOk();
        $this->actingAs($user)->postJson("/tasks/{$parent->id}/update-status", ['status' => 'completed'])->assertOk();

        $this->assertSame('completed', $unfinishedPart->fresh()->status);
        $this->assertSame('completed', $parent->fresh()->status);
        $this->assertSame($donePart->id, TimeEntry::findOrFail($entryId)->task_id);
    }

    public function test_project_cannot_be_completed_while_its_subproject_or_tasks_are_open(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $childProject = Project::factory()->create([
            'user_id' => $user->id, 'parent_id' => $project->id, 'status' => 'in_progress',
        ]);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $childProject->id, 'status' => 'to_do']);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'status' => 'completed',
            'type' => 'project',
            'parent_id' => null,
        ])->assertUnprocessable();

        $this->assertSame('in_progress', $project->fresh()->status);
    }

    public function test_timer_outcome_can_complete_an_explicitly_selected_project_branch(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $subproject = Project::factory()->create([
            'user_id' => $user->id, 'parent_id' => $project->id, 'status' => 'in_progress',
        ]);
        $task = Task::factory()->create(['user_id' => $user->id, 'project_id' => $subproject->id]);

        $entryId = $this->actingAs($user)->postJson(route('time.start'), ['project_id' => $project->id])
            ->assertCreated()->json('active.id');
        $this->actingAs($user)->postJson(route('time.stop', $entryId))->assertOk();

        $this->actingAs($user)->postJson(route('time.outcome', $entryId), [
            'completed_task_ids' => [$task->id],
            'completed_project_ids' => [$subproject->id],
            'complete_root' => true,
            'target_type' => 'project',
            'target_id' => $subproject->id,
        ])->assertOk();

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame('completed', $subproject->fresh()->status);
        $this->assertSame('completed', $project->fresh()->status);
        $this->assertSame($subproject->id, TimeEntry::findOrFail($entryId)->project_id);
    }
}
