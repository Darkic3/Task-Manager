<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerWeekTest extends TestCase
{
    use RefreshDatabase;

    private function monday(): string
    {
        return now()->startOfWeek()->toDateString();
    }

    public function test_week_view_renders_clean_day_columns(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        Task::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Week planning task',
            'status' => 'to_do',
            'due_date' => $this->monday(),
            'time_period' => 'morning',
        ]);

        $html = $this->actingAs($user)
            ->get(route('planner.index', ['view' => 'week', 'date' => $this->monday()]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="plWeek"', $html);
        $this->assertStringContainsString('data-week-day="'.$this->monday().'"', $html);
        $this->assertStringContainsString('Week planning task', $html);
        $this->assertStringContainsString('data-week-task', $html);
        $this->assertStringContainsString('data-period-group="morning"', $html);
        $this->assertStringContainsString('data-week-add', $html);
        $this->assertStringContainsString('data-week-pop', $html);
        $this->assertStringContainsString('id="pwMoveMenu"', $html);
    }

    public function test_quick_add_task_with_week_view_returns_week_card(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('planner.quick-add.task'), [
            'title' => 'Week quickie',
            'date' => $this->monday(),
            'time_period' => 'evening',
            'view' => 'week',
        ]);

        $response->assertCreated()->assertJsonPath('ok', true);
        $response->assertJsonPath('group', 'evening');
        $this->assertStringContainsString('pw-task', $response->json('html'));
        $this->assertStringContainsString('Week quickie', $response->json('html'));
    }

    public function test_backlog_lists_only_unscheduled_open_tasks(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $inbox = Task::factory()->create([
            'user_id' => $user->id, 'title' => 'Inbox gem', 'status' => 'to_do', 'due_date' => null,
        ]);
        Task::factory()->create([
            'user_id' => $user->id, 'title' => 'Already scheduled', 'status' => 'to_do', 'due_date' => $this->monday(),
        ]);
        Task::factory()->create([
            'user_id' => $user->id, 'title' => 'Done one', 'status' => 'completed', 'due_date' => null,
        ]);
        Task::factory()->create([
            'user_id' => $other->id, 'title' => 'Foreign inbox', 'status' => 'to_do', 'due_date' => null,
        ]);

        $json = $this->actingAs($user)->getJson(route('planner.backlog'))->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertCount(1, $json['tasks']);
        $this->assertSame($inbox->id, $json['tasks'][0]['id']);
        $this->assertSame('Inbox gem', $json['tasks'][0]['title']);
    }

    public function test_backlog_search_filters_by_title(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'title' => 'Alpha inbox', 'status' => 'to_do', 'due_date' => null]);
        Task::factory()->create(['user_id' => $user->id, 'title' => 'Beta inbox', 'status' => 'to_do', 'due_date' => null]);

        $json = $this->actingAs($user)->getJson(route('planner.backlog', ['q' => 'Alpha']))->assertOk()->json();

        $this->assertCount(1, $json['tasks']);
        $this->assertSame('Alpha inbox', $json['tasks'][0]['title']);
    }

    public function test_schedule_task_moves_to_day_and_period(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id, 'title' => 'Move me', 'status' => 'to_do', 'due_date' => null,
        ]);
        $target = now()->startOfWeek()->addDays(3)->toDateString();

        $response = $this->actingAs($user)->postJson(route('planner.tasks.schedule', $task), [
            'due_date' => $target,
            'time_period' => 'afternoon',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $response->assertJsonPath('due_date', $target);
        $response->assertJsonPath('group', 'afternoon');
        $this->assertStringContainsString('pw-task', $response->json('html'));

        $task->refresh();
        $this->assertSame($target, $task->due_date->toDateString());
        $this->assertSame('afternoon', $task->time_period);
    }

    public function test_schedule_task_rejects_foreign_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $other->id, 'due_date' => null]);

        $this->actingAs($user)->postJson(route('planner.tasks.schedule', $task), [
            'due_date' => $this->monday(),
        ])->assertForbidden();
    }
}
