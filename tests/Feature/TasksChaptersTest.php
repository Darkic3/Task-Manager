<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksChaptersTest extends TestCase
{
    use RefreshDatabase;

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    private function makeChapter(User $user, Project $project): Task
    {
        $chapter = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Chapter One',
            'status' => 'in_progress',
        ]);

        Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'parent_id' => $chapter->id,
            'title' => 'Topic A',
            'status' => 'completed',
        ]);
        $topicB = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'parent_id' => $chapter->id,
            'title' => 'Topic B',
            'status' => 'to_do',
        ]);
        $topicC = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'parent_id' => $chapter->id,
            'title' => 'Topic C',
            'status' => 'in_progress',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'parent_id' => $topicC->id,
            'title' => 'Sub C1',
            'status' => 'completed',
        ]);

        return $chapter->fresh();
    }

    public function test_project_tasks_page_renders_chapter_sections_with_progress_counts(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $chapter = $this->makeChapter($user, $project);
        Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Standalone task',
            'status' => 'to_do',
        ]);

        $response = $this->actingAs($user)->get(route('projects.tasks.index', $project));

        $response->assertOk();
        // Chapters container + toggle button exist
        $response->assertSee('cu-chapters-view', false);
        $response->assertSee('data-view="chapters"', false);
        // Chapter section: 5 items (chapter + 3 topics + 1 sub), 2 completed
        $response->assertSee('data-chapter="ch-' . $chapter->id . '"', false);
        $response->assertSee('Chapter One');
        $response->assertSee('2/5', false);
        $response->assertSee('3 open');
        // Childless root falls under "Other tasks": 0/1
        $response->assertSee('Other tasks');
        $response->assertSee('0/1', false);
    }

    public function test_fully_completed_chapter_section_is_collapsed_by_default(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $chapter = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Done chapter',
            'status' => 'completed',
        ]);
        Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'parent_id' => $chapter->id,
            'title' => 'Done topic',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->get(route('projects.tasks.index', $project));

        $response->assertOk();
        $response->assertSee('cu-chapter collapsed', false);
        $response->assertSee('2/2', false);
    }

    public function test_chapter_rows_carry_attributes_needed_for_js_sync(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $chapter = $this->makeChapter($user, $project);

        $response = $this->actingAs($user)->get(route('projects.tasks.index', $project));

        $response->assertOk();
        $topic = Task::where('title', 'Topic B')->firstOrFail();
        // Every row exposes id/root/status so check-toggle, delete and
        // section-progress refresh can sync board <-> chapters without reload.
        $response->assertSee('data-id="' . $topic->id . '"', false);
        $response->assertSee('data-root="ch-' . $chapter->id . '"', false);
        $response->assertSee('data-status="to_do"', false);
        $response->assertSee('data-total="5"', false);
        $response->assertSee('data-done="2"', false);
    }

    public function test_completing_a_topic_updates_section_counts_on_rerender(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $this->makeChapter($user, $project);
        $topic = Task::where('title', 'Topic B')->firstOrFail();

        $this->actingAs($user)->postJson("/tasks/{$topic->id}/update-status", ['status' => 'completed'])
            ->assertOk();

        $response = $this->actingAs($user)->get(route('projects.tasks.index', $project));
        $response->assertOk();
        $response->assertSee('3/5', false);
        $response->assertSee('2 open');
    }

    public function test_update_status_endpoint_sets_completed_at(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'status' => 'to_do',
        ]);

        $this->actingAs($user)->postJson("/tasks/{$task->id}/update-status", ['status' => 'completed'])
            ->assertOk()
            ->assertJson(['message' => 'Task status updated successfully.']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_destroy_task_removes_it_from_chapters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $this->makeChapter($user, $project);
        $topic = Task::where('title', 'Topic B')->firstOrFail();

        $this->actingAs($user)->delete("/tasks/{$topic->id}")->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $topic->id]);
        $response = $this->actingAs($user)->get(route('projects.tasks.index', $project));
        $response->assertOk();
        $response->assertDontSee('Topic B');
        $response->assertSee('2/4', false);
    }

    public function test_global_tasks_page_also_renders_chapters(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $this->makeChapter($user, $project);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response->assertOk();
        $response->assertSee('cu-chapters-view', false);
        $response->assertSee('Chapter One');
    }
}
