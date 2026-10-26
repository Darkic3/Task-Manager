<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_long_project_names_clip_inside_cards(): void
    {
        $user = User::factory()->create();
        Project::factory()->create([
            'user_id' => $user->id,
            'name' => 'طیف اختلالات اسکیزوفرنی و سایر اختلالات سایکوتیک ' . str_repeat('خیلی طولانی ', 5),
            'status' => 'not_started',
        ]);

        $response = $this->actingAs($user)->get(route('projects.index'));

        $response->assertOk();
        // The text wrapper must shrink inside the flex row with real CSS.
        // (Bootstrap 5 has no `min-w-0` utility — the old class did nothing
        // and long titles overflowed the card.)
        $response->assertSee('cu-card-text', false);
        $response->assertDontSee('min-w-0', false);
        $response->assertSee('cu-card-name', false);
    }

    public function test_children_nest_inside_parent_group(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create([
            'user_id' => $user->id,
            'name' => 'روانشناسی رشد',
            'status' => 'in_progress',
            'parent_id' => null,
        ]);
        Project::factory()->create([
            'user_id' => $user->id, 'parent_id' => $root->id,
            'name' => 'فصل اول', 'type' => 'chapter', 'status' => 'not_started',
        ]);
        Project::factory()->create([
            'user_id' => $user->id, 'parent_id' => $root->id,
            'name' => 'فصل دوم', 'type' => 'chapter', 'status' => 'not_started',
        ]);
        Project::factory()->create([
            'user_id' => $user->id, 'name' => 'پروژه تنها', 'status' => 'not_started',
        ]);

        $html = $this->actingAs($user)->get(route('projects.index'))
            ->assertOk()->getContent();

        // Parent card carries the whole group search index.
        $this->assertStringContainsString('روانشناسی رشد', $html);
        $this->assertStringContainsString('فصل اول', $html);
        $this->assertStringContainsString('فصل دوم', $html);
        // Grid: children nested inside the parent card (one group only).
        $this->assertEquals(1, substr_count($html, '<div class="cu-sub-list'));
        $this->assertStringContainsString('cu-sub-row', $html);
        // List: child rows linked to their root group.
        $this->assertStringContainsString('data-root="'.$root->id.'"', $html);
        // Tree view still renders the hierarchy.
        $this->assertStringContainsString('id="cuTree"', $html);
    }
}
