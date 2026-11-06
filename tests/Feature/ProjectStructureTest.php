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

    public function test_task_move_between_nodes(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $ch1 = Project::factory()->create(['user_id' => $user->id, 'name' => 'Ch1', 'type' => 'chapter', 'parent_id' => $root->id]);
        $ch2 = Project::factory()->create(['user_id' => $user->id, 'name' => 'Ch2', 'type' => 'chapter', 'parent_id' => $root->id]);
        $task = \App\Models\Task::factory()->create(['user_id' => $user->id, 'project_id' => $ch1->id, 'title' => 'T']);

        $this->actingAs($user)->patchJson(route('tasks.move', $task), ['project_id' => $ch2->id, 'parent_id' => null])
            ->assertOk()->assertJsonPath('ok', true);

        $this->assertSame($ch2->id, $task->fresh()->project_id);
    }

    public function test_task_move_rejects_cycles_and_foreign_projects(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $task = \App\Models\Task::factory()->create(['user_id' => $user->id, 'project_id' => $root->id, 'title' => 'P']);
        $sub = \App\Models\Task::factory()->create(['user_id' => $user->id, 'project_id' => $root->id, 'parent_id' => $task->id, 'title' => 'S']);
        $otherProject = Project::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'X', 'parent_id' => null]);

        // Cannot move under own subtask.
        $this->actingAs($user)->patchJson(route('tasks.move', $task), ['parent_id' => $sub->id])->assertStatus(422);
        // Cannot move into another user's project.
        $this->actingAs($user)->patchJson(route('tasks.move', $task), ['project_id' => $otherProject->id])->assertStatus(422);
    }

    public function test_node_delete_returns_json_when_requested(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $ch = Project::factory()->create(['user_id' => $user->id, 'name' => 'Ch', 'type' => 'chapter', 'parent_id' => $root->id]);

        $this->actingAs($user)->deleteJson(route('projects.destroy', $ch))
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseMissing('projects', ['id' => $ch->id]);
    }

    public function test_task_inline_rename_via_update(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $task = \App\Models\Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $root->id, 'title' => 'Old', 'status' => 'completed',
        ]);

        $this->actingAs($user)->putJson(route('tasks.update', $task), [
            'title' => 'New', 'priority' => 'high', 'status' => 'completed',
        ])->assertOk();

        $fresh = $task->fresh();
        $this->assertSame('New', $fresh->title);
        $this->assertSame('completed', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_rename_node(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $ch = Project::factory()->create(['user_id' => $user->id, 'name' => 'Old Name', 'type' => 'chapter', 'parent_id' => $root->id]);

        $res = $this->actingAs($user)->patchJson(route('projects.nodes.rename', $ch), ['name' => 'New Name']);

        $res->assertOk()->assertJsonPath('ok', true)
            ->assertJsonPath('node.name', 'New Name');
        $fresh = $ch->fresh();
        $this->assertSame('New Name', $fresh->name);
        $this->assertStringContainsString('new-name', $fresh->slug);
        $this->assertStringContainsString('new-name', $res->json('node.url'));
    }

    public function test_rename_node_validation_and_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $ch = Project::factory()->create(['user_id' => $user->id, 'name' => 'Ch', 'type' => 'chapter', 'parent_id' => $root->id]);
        $foreign = Project::factory()->create(['user_id' => $other->id, 'name' => 'X', 'parent_id' => null]);

        $this->actingAs($user)->patchJson(route('projects.nodes.rename', $ch), ['name' => ''])->assertStatus(422);
        $this->actingAs($user)->patchJson(route('projects.nodes.rename', $ch), ['name' => str_repeat('a', 256)])->assertStatus(422);
        $this->actingAs($user)->patchJson(route('projects.nodes.rename', $foreign), ['name' => 'Hi'])->assertStatus(403);
        $this->assertSame('Ch', $ch->fresh()->name);
    }

    public function test_store_node_returns_rendered_html(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);

        $res = $this->actingAs($user)->postJson(route('projects.nodes.store', $root), [
            'name' => 'Chapter <b>One</b>', 'type' => 'chapter',
        ]);

        $res->assertOk()->assertJsonPath('ok', true);
        $html = $res->json('nodeHtml');
        $this->assertNotEmpty($html);
        // Rendered through the Blade partial: escaped name + node hook + type badge.
        $this->assertStringContainsString('data-node-id="' . $res->json('node.id') . '"', $html);
        $this->assertStringContainsString('Chapter &lt;b&gt;One&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>One</b>', $html);
    }

    public function test_show_page_embeds_structure_seed(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $ch = Project::factory()->create(['user_id' => $user->id, 'name' => 'Seed Chapter', 'type' => 'chapter', 'parent_id' => $root->id]);

        $res = $this->actingAs($user)->get(route('projects.show', $root));
        $res->assertOk();
        $res->assertSee('window.__PJ_STRUCTURE_INITIAL__', false);
        $res->assertSee('Seed Chapter', false);
        $res->assertSee('"id":' . $ch->id, false);

        // Leaf pages carry no seed (no structure island there).
        $leaf = Project::factory()->create(['user_id' => $user->id, 'name' => 'Solo', 'type' => 'lesson', 'parent_id' => null]);
        $res2 = $this->actingAs($user)->get(route('projects.show', $leaf));
        $res2->assertOk();
        $res2->assertDontSee('__PJ_STRUCTURE_INITIAL__', false);
    }

    public function test_rows_html_batch_renders_nodes(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $ch = Project::factory()->create(['user_id' => $user->id, 'name' => 'Row Chapter', 'type' => 'chapter', 'parent_id' => $root->id]);
        $other = User::factory()->create();
        $foreign = Project::factory()->create(['user_id' => $other->id, 'name' => 'X', 'parent_id' => null]);

        $res = $this->actingAs($user)->getJson(route('projects.rows.html', $root) . '?ids[]=' . $ch->id . '&ids[]=' . $root->id . '&ids[]=' . $foreign->id . '&ids[]=999999');

        $res->assertOk()->assertJsonPath('ok', true);
        $rows = $res->json('rows');
        $this->assertArrayHasKey((string) $ch->id, $rows);
        $this->assertStringContainsString('data-node-id="' . $ch->id . '"', $rows[(string) $ch->id]);
        $this->assertStringContainsString('Row Chapter', $rows[(string) $ch->id]);
        // Root has no row; foreign/missing ids are skipped, not errors.
        $this->assertArrayNotHasKey((string) $root->id, $rows);
        $this->assertArrayNotHasKey((string) $foreign->id, $rows);
        $this->assertArrayNotHasKey('999999', $rows);
    }

    public function test_rows_html_requires_ids(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);

        $this->actingAs($user)->getJson(route('projects.rows.html', $root))->assertStatus(422);
    }

    public function test_show_page_is_fully_persian_under_fa_locale(): void
    {
        $user = User::factory()->create(['locale' => 'fa']);
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'کتاب', 'type' => 'project', 'parent_id' => null]);
        $chapter = Project::factory()->create(['user_id' => $user->id, 'name' => 'فصل یک', 'type' => 'chapter', 'parent_id' => $root->id]);
        Project::factory()->create(['user_id' => $user->id, 'name' => 'درس آزاد', 'type' => 'lesson', 'parent_id' => $root->id]);
        \App\Models\Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $chapter->id, 'title' => 'تمرین یک',
        ]);
        \App\Models\Task::factory()->create([
            'user_id' => $user->id, 'project_id' => $root->id, 'title' => 'تسک ریشه',
        ]);

        $res = $this->actingAs($user)->get(route('projects.show', $root));
        $res->assertOk();
        // Persian labels replace every English section string on the page.
        foreach (['ساختار', 'فصل جدید', 'درس جدید', 'فصل‌ها و گروه‌ها', 'درس‌های بدون فصل', 'فصل', 'درس', 'تسک‌های مستقیم', 'همه تسک‌ها'] as $fa) {
            $res->assertSee($fa, false);
        }
        // No leftover English UI labels from the structure/tasks sections.
        foreach (['New chapter', 'New lesson', 'Ungrouped lessons', 'Chapters & groups', 'Board + full add', 'Quick add task', 'Move / more', 'Convert to', 'chapters & lessons'] as $en) {
            $res->assertDontSee($en, false);
        }
        // New popover + task actions are Persian too; old inline menu is gone.
        foreach (['انتقال به ریشه (بدون فصل)', 'تغییر نام', 'ویرایش کامل', 'بکش برای مرتب‌سازی', 'تسک را بکش تا به فصل دیگری منتقل شود'] as $fa) {
            $res->assertSee($fa, false);
        }
        $res->assertDontSee('data-st-menu-body', false);
        $res->assertDontSee('data-st-convert', false);
    }
}
