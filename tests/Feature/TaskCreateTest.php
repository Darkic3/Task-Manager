<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_loads(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('tasks.create'))->assertOk();
    }

    public function test_create_page_submits_the_fields_the_store_endpoint_requires(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('tasks.create'));

        $response->assertSee('name="user_id"', false);
        $response->assertSee('name="status"', false);
    }

    public function test_task_is_created_from_the_create_page_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), [
            'user_id' => $user->id,
            'status' => 'to_do',
            'title' => 'Buy groceries',
            'description' => '<p>Milk and bread</p>',
            'priority' => 'medium',
        ]);

        $response->assertRedirect(route('tasks.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Buy groceries',
            'status' => 'to_do',
        ]);
    }

    public function test_user_id_and_status_fall_back_to_defaults_when_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Minimal payload',
            'priority' => 'high',
        ]);

        $response->assertRedirect(route('tasks.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Minimal payload',
            'status' => 'to_do',
        ]);
    }

    public function test_estimate_split_is_combined_into_estimated_hours(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Estimate test',
            'priority' => 'low',
            'est_hours' => 2,
            'est_minutes' => 30,
        ])->assertSessionHasNoErrors();

        $task = Task::where('title', 'Estimate test')->firstOrFail();

        $this->assertEqualsWithDelta(2.5, (float) $task->estimated_hours, 0.001);
    }

    public function test_title_is_still_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'priority' => 'medium',
        ])->assertSessionHasErrors('title');
    }

    public function test_another_users_project_cannot_be_used(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignProject = \App\Models\Project::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Foreign project',
            'priority' => 'medium',
            'project_id' => $foreignProject->id,
        ])->assertSessionHasErrors('project_id');
    }
}