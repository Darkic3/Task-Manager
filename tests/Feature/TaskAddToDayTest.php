<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAddToDayTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $user, array $attrs = []): Task
    {
        $project = Project::factory()->create(['user_id' => $user->id]);

        return Task::factory()->create(array_merge([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ], $attrs));
    }

    public function test_add_to_day_sets_due_date_and_period(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user, ['due_date' => now()->addDays(3)->toDateString()]);

        $this->actingAs($user)->postJson(route('tasks.add-to-day', $task), [
            'time_period' => 'morning',
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('period_label', 'Morning');

        $task->refresh();
        $this->assertSame(now()->toDateString(), $task->due_date->toDateString());
        $this->assertSame('morning', $task->time_period);
    }

    public function test_add_to_day_without_period_is_anytime(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user);

        $this->actingAs($user)->postJson(route('tasks.add-to-day', $task), [])
            ->assertOk()
            ->assertJsonPath('period_label', 'Anytime');

        $task->refresh();
        $this->assertNull($task->time_period);
        $this->assertSame(now()->toDateString(), $task->due_date->toDateString());
    }

    public function test_add_to_day_rejects_foreign_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = $this->task($other);

        $this->actingAs($user)->postJson(route('tasks.add-to-day', $task), [
            'time_period' => 'morning',
        ])->assertForbidden();
    }

    public function test_add_to_day_rejects_invalid_period(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user);

        $this->actingAs($user)->postJson(route('tasks.add-to-day', $task), [
            'time_period' => 'midnight',
        ])->assertStatus(422);
    }

    public function test_add_to_day_applies_to_custom_date(): void
    {
        $user = User::factory()->create();
        $task = $this->task($user);

        $this->actingAs($user)->postJson(route('tasks.add-to-day', $task), [
            'time_period' => 'evening',
            'date' => now()->addDay()->toDateString(),
        ])->assertOk();

        $task->refresh();
        $this->assertSame(now()->addDay()->toDateString(), $task->due_date->toDateString());
    }

    public function test_tasks_page_renders_add_to_day_buttons(): void
    {
        $user = User::factory()->create();
        $open = $this->task($user, ['title' => 'Plannable task', 'status' => 'to_do']);
        $finished = $this->task($user, ['title' => 'Finished task', 'status' => 'completed']);

        $response = $this->actingAs($user)->get(route('tasks.index'));
        $response->assertOk();
        $response->assertSee('Plannable task');

        $html = $response->getContent();
        $this->assertStringContainsString('addToDayModal', $html);
        $this->assertStringContainsString('data-add-day data-id', $html);
        /* The open task carries the trigger in every view; the completed task gets none */
        $this->assertStringContainsString('data-add-day data-id="' . $open->id . '"', $html);
        $this->assertSame(0, substr_count($html, 'data-add-day data-id="' . $finished->id . '"'));
    }

    public function test_planner_day_shows_planned_period_chip(): void
    {
        $user = User::factory()->create();
        $this->task($user, ['title' => 'Morning run', 'due_date' => now()->toDateString(), 'time_period' => 'morning']);

        $response = $this->actingAs($user)->get(route('planner.index'));
        $response->assertOk();
        $response->assertSee('Morning run');
        $response->assertSee('Morning', false);
    }
}
