<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerQuickAddTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_add_task_defaults_to_inbox_and_selected_day(): void
    {
        $user = User::factory()->create();
        $date = now()->toDateString();

        $response = $this->actingAs($user)->postJson(route('planner.quick-add.task'), [
            'title' => 'Buy milk',
            'date' => $date,
        ]);

        $response->assertCreated()->assertJsonPath('ok', true);

        $task = Task::where('user_id', $user->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('Buy milk', $task->title);
        $this->assertSame('to_do', $task->status);
        $this->assertSame('medium', $task->priority);
        $this->assertSame($date, $task->due_date->toDateString());

        $inbox = Project::where('user_id', $user->id)->where('name', 'Inbox')->first();
        $this->assertNotNull($inbox);
        $this->assertSame('inbox', $inbox->type);
        $this->assertSame($inbox->id, $task->project_id);

        $response->assertSee('Buy milk');
    }

    public function test_quick_add_task_uses_own_project_and_time_fields(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('planner.quick-add.task'), [
            'title' => 'Standup',
            'date' => $date,
            'project_id' => $project->id,
            'priority' => 'high',
            'time_period' => 'morning',
            'due_time' => '09:30',
            'estimated_minutes' => 30,
        ])->assertCreated();

        $task = Task::where('user_id', $user->id)->first();
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame('high', $task->priority);
        $this->assertSame('morning', $task->time_period);
        $this->assertSame('09:30:00', substr((string) $task->due_time, 0, 8));
        $this->assertEquals(0.5, (float) $task->estimated_hours);
    }

    public function test_quick_add_task_rejects_foreign_project(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->postJson(route('planner.quick-add.task'), [
            'title' => 'Sneaky',
            'project_id' => $project->id,
        ])->assertStatus(422);

        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_quick_add_task_requires_title(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('planner.quick-add.task'), [
            'title' => '',
        ])->assertStatus(422);
    }

    public function test_quick_add_routine_defaults_to_daily(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('planner.quick-add.routine'), [
            'title' => 'Stretch',
            'date' => now()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('ok', true);

        $routine = Routine::where('user_id', $user->id)->first();
        $this->assertNotNull($routine);
        $this->assertSame('daily', $routine->frequency);
        $this->assertSame('Stretch', $routine->title);

        $response->assertSee('Stretch');
    }

    public function test_quick_add_weekly_routine_defaults_to_selected_weekday(): void
    {
        $user = User::factory()->create();
        $date = now()->startOfWeek()->addDays(2); // a deterministic weekday

        $this->actingAs($user)->postJson(route('planner.quick-add.routine'), [
            'title' => 'Review',
            'date' => $date->toDateString(),
            'frequency' => 'weekly',
            'time_period' => 'evening',
        ])->assertCreated();

        $routine = Routine::where('user_id', $user->id)->first();
        $this->assertSame('weekly', $routine->frequency);
        $this->assertSame([strtolower($date->format('l'))], $routine->days);
        $this->assertSame('evening', $routine->time_period);
    }

    public function test_quick_add_requires_authentication(): void
    {
        $this->postJson(route('planner.quick-add.task'), ['title' => 'Nope'])
            ->assertUnauthorized();
    }
}
