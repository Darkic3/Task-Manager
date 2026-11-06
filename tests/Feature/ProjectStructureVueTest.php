<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contract for the project-structure Vue island (planner-island pattern):
 * vendored Vue build, versioned island script, JSON seed bootstrapped from
 * Blade, and a bridge the vanilla flows call instead of reloading.
 */
class ProjectStructureVueTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_bootstraps_structure_island(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        $ch = Project::factory()->create(['user_id' => $user->id, 'name' => 'Chapter "یک" \\ intro', 'type' => 'chapter', 'parent_id' => $root->id]);
        Project::factory()->create(['user_id' => $user->id, 'name' => 'Lesson', 'type' => 'lesson', 'parent_id' => $ch->id]);

        $html = $this->actingAs($user)->get(route('projects.show', $root))->assertOk()->getContent();

        // Island contract: seed + vendored lib + island app + mount point.
        $this->assertStringContainsString('__PJ_STRUCTURE_INITIAL__', $html);
        $this->assertStringContainsString('assets/vendor/vue/vue.global.prod.js', $html);
        $this->assertStringContainsString('assets/js/project-structure-app.js', $html);
        $this->assertStringContainsString('id="st-body"', $html);

        // The seed must be pure, parseable JSON (exactly what the browser
        // evaluates) — including tricky real-world titles.
        preg_match('/window\.__PJ_STRUCTURE_INITIAL__\s*=\s*(\{.*?\});\s*<\/script>/s', $html, $m);
        $this->assertNotEmpty($m, 'bootstrap JSON block not found');
        $data = json_decode($m[1], true);
        $this->assertNotNull($data, 'bootstrap payload is not valid JSON: ' . json_last_error_msg());
        $this->assertSame('Book', $data['name']);
        $this->assertCount(1, $data['children']);
        $this->assertSame('Chapter "یک" \\ intro', $data['children'][0]['name']);
        $this->assertCount(1, $data['children'][0]['children']);
        $this->assertSame(0, substr_count(strtolower($m[1]), '</script'), 'script breakout in payload');
    }

    public function test_island_assets_and_bridge_contract(): void
    {
        $vendor = public_path('assets/vendor/vue/vue.global.prod.js');
        $this->assertFileExists($vendor);
        $this->assertStringContainsString('createApp', (string) file_get_contents($vendor));

        $app = public_path('assets/js/project-structure-app.js');
        $this->assertFileExists($app);
        $js = (string) file_get_contents($app);
        // Bridge contract the Blade flows rely on: mount flag, server
        // reconcile (structure data + batch row HTML), seed/i18n bootstraps.
        foreach (['ProjectStructure', 'mounted', 'rowsUrl', '__PJ_STRUCTURE_INITIAL__', '__PJ_I18N__', 'st-body', 'v-html', '__stApplyCollapsed'] as $marker) {
            $this->assertStringContainsString($marker, $js);
        }
    }

    public function test_island_scripts_are_versioned(): void
    {
        $user = User::factory()->create();
        $root = Project::factory()->create(['user_id' => $user->id, 'name' => 'Book', 'parent_id' => null]);
        Project::factory()->create(['user_id' => $user->id, 'name' => 'Ch', 'type' => 'chapter', 'parent_id' => $root->id]);

        $html = $this->actingAs($user)->get(route('projects.show', $root))->assertOk()->getContent();
        $this->assertStringContainsString('assets/vendor/vue/vue.global.prod.js?v=', $html);
        $this->assertStringContainsString('assets/js/project-structure-app.js?v=', $html);
    }

    public function test_leaf_page_has_no_island_bootstrap(): void
    {
        $user = User::factory()->create();
        $leaf = Project::factory()->create(['user_id' => $user->id, 'name' => 'Solo', 'type' => 'lesson', 'parent_id' => null]);

        $html = $this->actingAs($user)->get(route('projects.show', $leaf))->assertOk()->getContent();
        $this->assertStringNotContainsString('__PJ_STRUCTURE_INITIAL__', $html);
        $this->assertStringNotContainsString('project-structure-app.js', $html);
    }
}
