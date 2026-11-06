<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_edit_form_renders_minimal_layout(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'status' => 'in_progress']);
        $parent = Task::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'title' => 'Parent task']);
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'parent_id' => $parent->id,
            'title' => 'Read chapter one',
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($user)->get(route('tasks.edit', $task));

        $response->assertOk();
        $response->assertSee('Read chapter one');
        // Fields present with current values selected (regex: checked on the right radio)
        $this->assertMatchesRegularExpression('/id="status_in_progress"[^>]*checked/s', $response->getContent());
        $response->assertSee('id="priority_high"', false);
        $response->assertSee('Parent task', false);
        $response->assertSee('More options', false);
        $response->assertSee('quill-editor', false);
        // Danger zone kept but minimal (no red section header)
        $response->assertSee('Delete this task');
        // Minimal look: no gradient header, no info panel, no boxed sections
        $response->assertDontSee('content-header', false);
        $response->assertDontSee('cu-info-panel', false);
        $response->assertDontSee('cu-section-icon', false);
    }

    public function test_project_edit_back_link_returns_to_previous_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Child']);
        $indexUrl = route('projects.index');

        $html = $this->actingAs($user)->withHeaders(['Referer' => $indexUrl])
            ->get(route('projects.edit', $project))->assertOk()->getContent();

        // Back link, cancel button and the return_to field all target the index.
        $this->assertStringContainsString('href="' . $indexUrl . '"', $html);
        $this->assertStringContainsString('name="return_to" value="' . $indexUrl . '"', $html);
    }

    public function test_project_edit_explicit_return_to_wins_over_referer(): void
    {
        $user = User::factory()->create();
        $parent = Project::factory()->create(['user_id' => $user->id, 'name' => 'Parent']);
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Child', 'parent_id' => $parent->id]);
        $parentUrl = route('projects.show', $parent);

        $html = $this->actingAs($user)->withHeaders(['Referer' => route('projects.index')])
            ->get(route('projects.edit', [$project, 'return_to' => $parentUrl]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('href="' . $parentUrl . '"', $html);
        $this->assertStringContainsString('name="return_to" value="' . $parentUrl . '"', $html);
    }

    public function test_project_update_redirects_to_return_to(): void
    {
        $user = User::factory()->create();
        $parent = Project::factory()->create(['user_id' => $user->id, 'name' => 'Parent']);
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Child']);
        $parentUrl = route('projects.show', $parent);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'Child',
            'status' => 'in_progress',
            'return_to' => $parentUrl,
        ])->assertRedirect($parentUrl);
    }

    public function test_project_update_rejects_external_return_to(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Child']);

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'Child',
            'status' => 'in_progress',
            'return_to' => 'https://evil.example/phish',
        ])->assertRedirect(route('projects.index'));

        $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => 'Child',
            'status' => 'in_progress',
            'return_to' => '//evil.example/phish',
        ])->assertRedirect(route('projects.index'));
    }

    public function test_edit_accepts_return_url_on_request_host(): void
    {
        // Real-world setup: APP_URL is localhost but the app is browsed via
        // a custom domain. A referer/return_to on the request host must win
        // over the projects index fallback.
        config(['app.url' => 'http://localhost']);
        $user = User::factory()->create();
        $parent = Project::factory()->create(['user_id' => $user->id, 'name' => 'Parent']);
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Klah', 'parent_id' => $parent->id]);
        $parentUrl = 'http://task-manager.test/projects/' . $parent->slug;
        $editUrl = 'http://task-manager.test/projects/' . $project->slug . '/edit';

        $html = $this->actingAs($user)
            ->withHeaders(['Referer' => $parentUrl])
            ->get($editUrl)->assertOk()->getContent();

        $this->assertStringContainsString('href="' . $parentUrl . '"', $html);

        $this->actingAs($user)
            ->put('http://task-manager.test/projects/' . $project->slug, [
                'name' => 'Klah',
                'status' => 'in_progress',
                'return_to' => $parentUrl,
            ])->assertRedirect($parentUrl);
    }

    public function test_edit_entry_links_carry_return_to(): void
    {
        $user = User::factory()->create();
        $parent = Project::factory()->create(['user_id' => $user->id, 'name' => 'Parent']);

        $showHtml = $this->actingAs($user)->get(route('projects.show', $parent))->assertOk()->getContent();
        $this->assertStringContainsString('return_to=', $showHtml);

        $indexHtml = $this->actingAs($user)->get(route('projects.index'))->assertOk()->getContent();
        $this->assertStringContainsString('return_to=', $indexHtml);
    }

    public function test_project_edit_falls_back_to_show_page(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'name' => 'Child']);

        // External referer must never leak into the back link.
        $html = $this->actingAs($user)->withHeaders(['Referer' => 'https://evil.example/phish'])
            ->get(route('projects.edit', $project))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('projects.show', $project) . '"', $html);
    }

    public function test_project_edit_form_renders_minimal_layout(): void
    {
        $user = User::factory()->create();
        $parent = Project::factory()->create(['user_id' => $user->id, 'name' => 'Konkur, Arshad']);
        $project = Project::factory()->create([
            'user_id' => $user->id, 'name' => 'Study Project', 'status' => 'in_progress',
            'parent_id' => $parent->id,
        ]);

        $response = $this->actingAs($user)->get(route('projects.edit', $project));

        $response->assertOk();
        $response->assertSee('Study Project');
        $response->assertSee('id="status_in_progress" value="in_progress"', false);
        $this->assertMatchesRegularExpression('/id="status_in_progress"[^>]*checked/s', $response->getContent());
        $response->assertSee('>Konkur, Arshad</option>', false);
        $response->assertSee('Sub-project', false);
        $response->assertSee('end_date', false);
        $response->assertSee('budget', false);
        $response->assertSee('quill-editor', false);
        $response->assertSee('Delete this project');
        // Minimal look: no gradient header, no info panel, no boxed sections
        $response->assertDontSee('content-header', false);
        $response->assertDontSee('cu-info-panel', false);
        $response->assertDontSee('cu-section-icon', false);
    }
}
