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
