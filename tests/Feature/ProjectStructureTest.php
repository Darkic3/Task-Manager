<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_structure_endpoint_returns_nested_tree(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $chapter = Project::factory()->create(['user_id' => $user->id, 'name' => 'Ch 1', 'type' => 'chapter', 'parent_id' => $root->id]);
        Project::factory()->create(['user_id' => $user->id, 'name' => 'Lesson 1', 'type' => 'lesson', 'parent_id' => $chapter->id]);

        $res = $this->actingAs($user)->getJson(route('projects.structure', $root));

        $res->assertOk()->assertJsonPath('ok', true);
        $tree = $res->json('tree');
        $this->assertSame('Book', $tree['name']);
        $this->assertCount(1, $tree['children']);
        $this->assertSame('Ch 1', $tree['children'][0]['name']);
        $this->assertCount(1, $tree['children'][0]['children']);
    }

    public function test_store_node_under_chapter_and_move_lesson_into_it(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $lesson = Project::factory()->create(['user_id' => $user->id, 'name' => 'L1', 'type' => 'lesson', 'parent_id' => $root->id]);

        // Create a chapter at root.
        $created = $this->actingAs($user)->postJson(route('projects.nodes.store', $root), [
            'name' => 'Chapter A', 'type' => 'chapter',
        ]);
        $created->assertOk()->assertJsonPath('ok', true);
        $chapterId = $created->json('node.id');
        $this->assertNotNull($chapterId);

        // Move the ungrouped lesson into the chapter.
        $moved = $this->actingAs($user)->patchJson(route('projects.nodes.move', $root), [
            'ids' => [$lesson->id], 'destination_id' => $chapterId,
        ]);
        $moved->assertOk()->assertJsonPath('ok', true);
        $this->assertSame($chapterId, $lesson->fresh()->parent_id);
    }

    public function test_move_rejects_cycles_and_outside_destinations(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $chapter = Project::factory()->create(['user_id' => $user->id, 'name' => 'Ch', 'type' => 'chapter', 'parent_id' => $root->id]);
        $lesson = Project::factory()->create(['user_id' => $user->id, 'name' => 'L', 'type' => 'lesson', 'parent_id' => $chapter->id]);
        $other = Project::factory()->create(['user_id' => $user->id, 'name' => 'Other', 'parent_id' => null]);

        // Cannot move a chapter under its own lesson (cycle).
        $this->actingAs($user)->patchJson(route('projects.nodes.move', $root), [
            'ids' => [$chapter->id], 'destination_id' => $lesson->id,
        ])->assertStatus(422);

        // Cannot move a node from another tree.
        $this->actingAs($user)->patchJson(route('projects.nodes.move', $root), [
            'ids' => [$other->id], 'destination_id' => $chapter->id,
        ])->assertStatus(422);

        // Cannot move the root itself.
        $this->actingAs($user)->patchJson(route('projects.nodes.move', $root), [
            'ids' => [$root->id], 'destination_id' => $chapter->id,
        ])->assertStatus(422);
    }

    public function test_convert_node_type(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $lesson = Project::factory()->create(['user_id' => $user->id, 'name' => 'L', 'type' => 'lesson', 'parent_id' => $root->id]);

        $this->actingAs($user)->patchJson(route('projects.nodes.convert', $lesson), ['type' => 'chapter'])
            ->assertOk()->assertJsonPath('node.type', 'chapter');

        $this->assertSame('chapter', $lesson->fresh()->type);
    }

    public function test_show_page_renders_structure_manager(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        Project::factory()->create(['user_id' => $user->id, 'name' => 'L1', 'type' => 'lesson', 'parent_id' => $root->id]);

        $res = $this->actingAs($user)->get(route('projects.show', $root));
        $res->assertOk();
        $res->assertSee('st-manager', false);
        $res->assertSee('Ungrouped', false);
    }

    public function test_lesson_page_shows_its_own_tasks(): void
    {
        $user = User::factory()->create();
        $lesson = Project::factory()->create(['user_id' => $user->id, 'name' => 'Dastan', 'type' => 'lesson', 'parent_id' => null]);
        $task = \App\Models\Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $lesson->id, 'title' => 'Read pages 1-10',
        ]);
        \App\Models\Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $lesson->id, 'parent_id' => $task->id, 'title' => 'Summarize point A',
        ]);

        $res = $this->actingAs($user)->get(route('projects.show', $lesson));
        $res->assertOk();
        // Own tasks are rendered (root + subtask), quick-add present, structure manager hidden for leaf.
        $res->assertSee('Read pages 1-10', false);
        $res->assertSee('Summarize point A', false);
        $res->assertSee('pt-manager', false);
        $res->assertSee('pt-quickinput', false);
        $res->assertDontSee('st-manager', false);
    }

    public function test_container_page_shows_both_structure_and_direct_tasks(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        Project::factory()->create(['user_id' => $user->id, 'name' => 'Ch1', 'type' => 'chapter', 'parent_id' => $root->id]);
        \App\Models\Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $root->id, 'title' => 'Root level task',
        ]);

        $res = $this->actingAs($user)->get(route('projects.show', $root));
        $res->assertOk();
        $res->assertSee('st-manager', false);
        $res->assertSee('pt-manager', false);
        $res->assertSee('Root level task', false);
    }
}
