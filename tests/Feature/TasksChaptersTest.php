<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TasksChaptersTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_tests_run_against_the_dedicated_test_database(): void
    {
        // Guard: never run the suite against the development database.
        $this->assertSame('task_test', DB::getDatabaseName());
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

    public function test_destroy_returns_json_for_ajax_requests(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        // AJAX deletes must answer with 200 JSON, not a 302: fetch() re-issues
        // DELETE against the redirect target (405), and the old form fallback
        // then posted to the deleted task's URL, landing the user on a 404.
        $this->actingAs($user)
            ->deleteJson("/tasks/{$task->id}")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
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

    public function test_global_tasks_page_renders_project_sections(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id, 'name' => 'Study Project', 'status' => 'in_progress',
        ]);
        $this->makeChapter($user, $project);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response->assertOk();
        $response->assertSee('data-view="projects"', false);
        $response->assertSee('data-chapter="p-' . $project->id . '"', false);
        $response->assertSee('Study Project');
        $response->assertSee(route('projects.tasks.index', $project), false);
    }

    public function test_bulk_update_changes_status_of_owned_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $a = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'status' => 'to_do']);
        $b = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'status' => 'in_progress']);
        $other = User::factory()->create();
        $foreign = Task::factory()->create(['user_id' => $other->id, 'status' => 'to_do']);

        $this->actingAs($user)
            ->postJson(route('tasks.bulk-update'), ['ids' => [$a->id, $b->id, $foreign->id], 'status' => 'completed'])
            ->assertOk()
            ->assertJson(['ok' => true, 'updated' => 2]);

        $this->assertDatabaseHas('tasks', ['id' => $a->id, 'status' => 'completed']);
        $this->assertDatabaseHas('tasks', ['id' => $b->id, 'status' => 'completed']);
        // Another user's task is untouched.
        $this->assertDatabaseHas('tasks', ['id' => $foreign->id, 'status' => 'to_do']);
    }

    public function test_bulk_destroy_deletes_only_owned_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $a = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);
        $other = User::factory()->create();
        $foreign = Task::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->deleteJson(route('tasks.bulk-destroy'), ['ids' => [$a->id, $foreign->id]])
            ->assertOk()
            ->assertJson(['ok' => true, 'deleted' => 1]);

        $this->assertDatabaseMissing('tasks', ['id' => $a->id]);
        $this->assertDatabaseHas('tasks', ['id' => $foreign->id]);
    }

    public function test_bulk_endpoints_require_authentication(): void
    {
        $this->postJson(route('tasks.bulk-update'), ['ids' => [1], 'status' => 'completed'])
            ->assertUnauthorized();
        $this->deleteJson(route('tasks.bulk-destroy'), ['ids' => [1]])
            ->assertUnauthorized();
    }

    public function test_column_collapse_chevron_is_clickable_and_not_swallowed(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);

        $response = $this->actingAs($user)->get(route('tasks.index'));

        $response->assertOk();
        // The chevron must NOT share the .cu-col-add class: the column-head
        // click handler ignores .cu-col-add (the "+" modal button), which
        // used to swallow chevron clicks and made collapse dead.
        $response->assertSee('cu-col-chevron-btn', false);
        $response->assertDontSee('cu-col-add" data-chevron', false);
    }

    public function test_reorder_persists_order_without_detaching_children(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $parent = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);
        $child = Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $project->id, 'parent_id' => $parent->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('tasks.reorder'), ['items' => [
                ['id' => $child->id, 'parent_id' => $parent->id, 'sort_order' => 0],
                ['id' => $parent->id, 'parent_id' => null, 'sort_order' => 1],
            ]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('tasks', ['id' => $child->id, 'parent_id' => $parent->id, 'sort_order' => 0]);
    }
}
