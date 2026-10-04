<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskQuickStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_store_creates_task_with_defaults(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(route('tasks.quick-store'), [
            'title' => 'Inline task',
            'project_id' => $project->id,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $json = $response->json();
        $this->assertNotEmpty($json['rowHtml']);
        $this->assertNotEmpty($json['cardHtml']);
        $this->assertNotEmpty($json['listHtml']);
        $this->assertNotEmpty($json['treeHtml']);
        $this->assertStringContainsString('Inline task', $json['rowHtml']);

        $task = Task::find($json['id']);
        $this->assertNotNull($task);
        $this->assertSame('Inline task', $task->title);
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame($user->id, $task->user_id);
        $this->assertSame('medium', $task->priority);
        $this->assertSame('to_do', $task->status);
    }

    public function test_quick_store_rejects_foreign_project(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Project::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->postJson(route('tasks.quick-store'), [
            'title' => 'Nope',
            'project_id' => $foreign->id,
        ])->assertStatus(422);
    }

    public function test_quick_store_requires_title(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('tasks.quick-store'), [
            'title' => '',
        ])->assertStatus(422);
    }

    public function test_projects_view_renders_quick_add_buttons(): void
    {
        $user = User::factory()->create(['locale' => 'fa']);
        $project = Project::factory()->create(['user_id' => $user->id]);
        Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'تسک موجود',
            'status' => 'to_do',
        ]);

        $html = $this->actingAs($user)->get(route('tasks.index'))->getContent();

        $this->assertStringContainsString('data-ch-quickadd="'.$project->id.'"', $html);
        $this->assertStringContainsString('data-ch-quickform="p-'.$project->id.'"', $html);
    }
}
