<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\RoutineLog;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function valueRoutine(User $user): Routine
    {
        return Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'daily',
            'title' => 'Weigh in',
            'tracking_mode' => 'value',
            'value_kind' => 'weight',
            'value_unit' => 'kg',
            'value_label' => 'Weight',
        ]);
    }

    private function setsRoutine(User $user): Routine
    {
        $routine = Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'weekly',
            'title' => 'PULL A',
            'tracking_mode' => 'sets',
        ]);
        $routine->checklistItems()->create(['user_id' => $user->id, 'name' => 'Pull-up', 'sort_order' => 0, 'target_sets' => 2]);
        $routine->checklistItems()->create(['user_id' => $user->id, 'name' => 'Row', 'sort_order' => 1, 'target_sets' => 1]);

        return $routine;
    }

    public function test_value_log_endpoint_stores_and_updates(): void
    {
        $user = User::factory()->create();
        $routine = $this->valueRoutine($user);

        $this->actingAs($user)->postJson(route('planner.routines.log', $routine), [
            'date' => now()->toDateString(), 'value' => 80.5,
        ])->assertOk()->assertJsonPath('values.value', 80.5);

        // Re-logging the same day updates instead of duplicating.
        $this->actingAs($user)->postJson(route('planner.routines.log', $routine), [
            'date' => now()->toDateString(), 'value' => 79.9,
        ])->assertOk();
        $this->assertEquals(1, RoutineLog::where('routine_id', $routine->id)->count());
        $this->assertEquals(79.9, (float) RoutineLog::first()->value);
    }

    public function test_log_endpoint_rejects_foreign_and_untracked(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $routine = $this->valueRoutine($other);
        $plain = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);

        $this->actingAs($user)->postJson(route('planner.routines.log', $routine), [
            'date' => now()->toDateString(), 'value' => 80,
        ])->assertForbidden();

        $this->actingAs($user)->postJson(route('planner.routines.log', $plain), [
            'date' => now()->toDateString(), 'value' => 80,
        ])->assertStatus(422);
    }

    public function test_sets_logging_autocompletes_routine(): void    {
        $user = User::factory()->create();
        $routine = $this->setsRoutine($user);
        $items = $routine->checklistItems()->orderBy('id')->get();
        $date = now()->toDateString();

        // First step fully logged, second not yet → routine stays open.
        $this->actingAs($user)->postJson(route('planner.routines.log', $routine), [
            'date' => $date, 'item_id' => $items[0]->id, 'sets' => [1 => 8, 2 => 6],
        ])->assertOk()->assertJsonPath('routine_completed', false);

        $this->actingAs($user)->postJson(route('planner.routines.log', $routine), [
            'date' => $date, 'item_id' => $items[1]->id, 'sets' => [1 => 12],
        ])->assertOk()->assertJsonPath('routine_completed', true);

        $this->assertTrue($routine->fresh()->completedOn($date));
    }

    public function test_new_cycle_duplicates_and_archives(): void
    {
        $user = User::factory()->create();
        $routine = $this->setsRoutine($user);

        $this->actingAs($user)->post(route('routines.new-cycle', $routine))
            ->assertRedirect(route('routines.edit', Routine::where('cycle_no', 2)->firstOrFail()));

        $cycle2 = Routine::where('cycle_no', 2)->firstOrFail();
        $this->assertEquals($routine->id, $cycle2->parent_id);
        $this->assertEquals('sets', $cycle2->tracking_mode);
        $this->assertEquals(2, $cycle2->checklistItems()->count());
        $this->assertEquals(3, $cycle2->checklistItems()->sum('target_sets')); // 2 + default 1
        $this->assertSoftDeleted('routines', ['id' => $routine->id]);
    }

    public function test_track_page_renders_cards(): void
    {
        $user = User::factory()->create();
        $routine = $this->valueRoutine($user);
        RoutineLog::logValue($user->id, $routine->id, now(), 80.5);

        $this->actingAs($user)->get(route('track.index'))
            ->assertOk()
            ->assertSee('Weigh in')
            ->assertSee('80.5');
    }

    public function test_routine_log_tool_validates_and_executes(): void
    {
        $user = User::factory()->create();
        $routine = $this->valueRoutine($user);
        $svc = new AiToolService;

        $check = $svc->validateCall('routine_log', ['routine' => 'Weigh', 'value' => 81], $user);
        $this->assertTrue($check['ok']);
        $result = $svc->execute('routine_log', $check['resolved'], $user);
        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('routine_logs', ['routine_id' => $routine->id, 'value' => 81]);

        $bad = $svc->validateCall('routine_log', ['routine_id' => $routine->id, 'value' => 'abc'], $user);
        $this->assertFalse($bad['ok']);
    }

    public function test_routine_create_tool_with_tracking_and_steps(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $check = $svc->validateCall('routine_create', [
            'title' => 'PULL A', 'frequency' => 'weekly', 'days' => ['saturday'],
            'tracking_mode' => 'sets',
            'steps' => [['name' => 'Pull-up', 'target_sets' => 4], ['name' => 'Row']],
        ], $user);
        $this->assertTrue($check['ok']);
        $result = $svc->execute('routine_create', $check['resolved'], $user);
        $this->assertTrue($result['ok']);

        $routine = Routine::find($result['id']);
        $this->assertEquals('sets', $routine->tracking_mode);
        $this->assertEquals(['saturday'], $routine->decodedDays());
        $this->assertEquals(4, $routine->checklistItems()->where('name', 'Pull-up')->first()->target_sets);

        // Value mode without kind is rejected (AI must ask the user first).
        $this->assertFalse($svc->validateCall('routine_create', [
            'title' => 'X', 'frequency' => 'daily', 'tracking_mode' => 'value',
        ], $user)['ok']);
    }

    public function test_step_tick_requires_logged_value_in_sets_mode(): void
    {
        $user = User::factory()->create();
        $routine = $this->setsRoutine($user);
        $item = $routine->checklistItems()->orderBy('id')->first();
        $date = now()->toDateString();

        // Ticking without any logged number is refused server-side.
        $this->actingAs($user)->postJson(
            route('planner.check-items.toggle', $item) . '?date=' . $date
        )->assertStatus(422);
        $this->assertFalse($item->fresh()->completedOn($date));

        // After logging a number, the step is auto-ticked.
        $this->actingAs($user)->postJson(route('planner.routines.log', $routine), [
            'date' => $date, 'item_id' => $item->id, 'sets' => [1 => 15],
        ])->assertOk()->assertJsonPath('steps_done.' . $item->id, true);
        $this->assertTrue($item->fresh()->completedOn($date));
    }

    public function test_routine_row_collapses_details_by_default(): void
    {
        $user = User::factory()->create();
        $this->setsRoutine($user);

        $this->actingAs($user)->get(route('planner.index', ['view' => 'day']))
            ->assertOk()
            ->assertSee('data-details', false)
            ->assertSee('pl-expand', false);
    }

    public function test_big_and_tracked_routines_use_modal(): void
    {
        $user = User::factory()->create();
        $this->valueRoutine($user); // tracked (value) → modal

        $big = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Big']);
        for ($i = 0; $i < 6; $i++) {
            $big->checklistItems()->create(['user_id' => $user->id, 'name' => 'Step '.$i, 'sort_order' => $i]);
        }

        $small = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Small']);
        for ($i = 0; $i < 3; $i++) {
            $small->checklistItems()->create(['user_id' => $user->id, 'name' => 'S'.$i, 'sort_order' => $i]);
        }

        $html = $this->actingAs($user)->get(route('planner.index', ['view' => 'day']))
            ->assertOk()
            ->assertSee('id="plRoutineModal"', false)
            ->getContent();

        // Tracked value routine + 6-step routine → modal; 3-step plain → accordion.
        $this->assertSame(2, substr_count($html, 'data-modal="1"'));
        $this->assertStringContainsString('pl-expand-modal', $html);
    }
}
