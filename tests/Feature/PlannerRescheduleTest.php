<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerRescheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_postpone_moves_a_task_to_tomorrow(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'due_date' => today(),
            'status' => 'to_do',
        ]);

        $response = $this->actingAs($user)->postJson(route('planner.tasks.postpone', $task), ['action' => 'tomorrow']);

        $response->assertOk()->assertJson(['ok' => true, 'action' => 'tomorrow']);
        $this->assertSame(today()->addDay()->toDateString(), $task->fresh()->due_date->toDateString());
    }

    public function test_postpone_pulls_an_overdue_task_into_today_and_returns_row_html(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'due_date' => today()->subDays(3),
            'status' => 'to_do',
        ]);

        $response = $this->actingAs($user)->postJson(route('planner.tasks.postpone', $task), ['action' => 'today']);

        $response->assertOk()->assertJsonPath('group', 'anytime');
        $this->assertStringContainsString($task->title, (string) $response->json('row_html'));
        $this->assertSame(today()->toDateString(), $task->fresh()->due_date->toDateString());
    }

    public function test_clear_removes_the_due_date(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id, 'due_date' => today()]);

        $this->actingAs($user)->postJson(route('planner.tasks.postpone', $task), ['action' => 'clear'])->assertOk();

        $this->assertNull($task->fresh()->due_date);
    }

    public function test_postpone_rejects_other_users_tasks(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $owner->id, 'due_date' => today()]);

        $this->actingAs($intruder)
            ->postJson(route('planner.tasks.postpone', $task), ['action' => 'tomorrow'])
            ->assertForbidden();
    }

    public function test_reorder_persists_manual_order_and_period_move(): void
    {
        $user = User::factory()->create();
        $first = Task::factory()->create(['user_id' => $user->id, 'due_date' => today()]);
        $second = Task::factory()->create(['user_id' => $user->id, 'due_date' => today(), 'time_period' => 'morning']);

        $this->actingAs($user)->postJson(route('planner.tasks.reorder'), [
            'items' => [
                ['id' => $second->id, 'sort_order' => 0, 'time_period' => null],
                ['id' => $first->id, 'sort_order' => 10, 'time_period' => 'evening'],
            ],
        ])->assertOk()->assertJson(['ok' => true, 'updated' => 2]);

        $this->assertNull($second->fresh()->time_period);
        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame('evening', $first->fresh()->time_period);
        $this->assertSame(10, $first->fresh()->sort_order);
    }

    public function test_reorder_ignores_tasks_of_other_users(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $foreignTask = Task::factory()->create(['user_id' => $foreign->id]);

        $this->actingAs($user)
            ->postJson(route('planner.tasks.reorder'), ['items' => [['id' => $foreignTask->id, 'sort_order' => 5]]])
            ->assertOk()
            ->assertJson(['updated' => 0]);
    }

    public function test_routines_reorder_persists_sort_order(): void
    {
        $user = User::factory()->create();
        $a = Routine::create(['user_id' => $user->id, 'title' => 'A', 'frequency' => 'daily', 'time_period' => 'morning']);
        $b = Routine::create(['user_id' => $user->id, 'title' => 'B', 'frequency' => 'daily', 'time_period' => 'morning']);

        $this->actingAs($user)->postJson(route('routines.reorder'), [
            'items' => [
                ['id' => $b->id, 'sort_order' => 0],
                ['id' => $a->id, 'sort_order' => 10],
            ],
        ])->assertOk()->assertJson(['ok' => true, 'updated' => 2]);

        $this->assertSame(10, $a->fresh()->sort_order);
        $this->assertSame(0, $b->fresh()->sort_order);
    }

    public function test_routines_page_orders_by_period_then_manual_order(): void
    {
        $user = User::factory()->create();
        Routine::create(['user_id' => $user->id, 'title' => 'Zed Evening', 'frequency' => 'daily', 'time_period' => 'evening']);
        Routine::create(['user_id' => $user->id, 'title' => 'Morning Second', 'frequency' => 'daily', 'time_period' => 'morning', 'sort_order' => 10]);
        Routine::create(['user_id' => $user->id, 'title' => 'Morning First', 'frequency' => 'daily', 'time_period' => 'morning', 'sort_order' => 0]);

        $response = $this->actingAs($user)->get(route('routines.index'));

        $response->assertOk();
        $routines = $response->viewData('routines');
        $this->assertSame(
            ['Morning First', 'Morning Second', 'Zed Evening'],
            $routines->pluck('title')->all()
        );
        $response->assertSee('Morning First', false);
    }
}
