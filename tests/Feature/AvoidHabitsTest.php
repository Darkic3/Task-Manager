<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\RoutineChecklistItem;
use App\Models\RoutineNote;
use App\Models\RoutineViolation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvoidHabitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_avoid_routine_forces_no_tracking(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('routines.store'), [
            'title' => 'No smoking',
            'frequency' => 'daily',
            'behavior_type' => 'avoid',
            'count_violations' => '1',
            'tracking_mode' => 'value',
            'value_kind' => 'number',
            'items' => [
                ['name' => 'Morning', 'time_period' => 'morning'],
                ['name' => 'Night', 'time_period' => 'night'],
            ],
        ])->assertRedirect();

        $routine = Routine::where('title', 'No smoking')->firstOrFail();
        $this->assertTrue($routine->isAvoid());
        $this->assertTrue((bool) $routine->count_violations);
        $this->assertSame('none', $routine->tracking_mode);
        $this->assertCount(2, $routine->checklistItems);
    }

    public function test_routine_level_slip_loses_the_day(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid']);
        $date = now()->toDateString();

        $this->assertFalse($routine->violatedOn($date));

        $this->actingAs($user)->postJson(route('planner.routines.slip', $routine), ['date' => $date])
            ->assertOk()
            ->assertJson(['ok' => true, 'violated' => true, 'day_qty' => 1]);

        $this->assertTrue($routine->fresh()->violatedOn($date));
    }

    public function test_quantity_only_when_count_mode(): void
    {
        $user = User::factory()->create();
        $counted = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid', 'count_violations' => true]);
        $plain = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid']);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('planner.routines.slip', $counted), ['date' => $date, 'quantity' => 5])
            ->assertOk();
        $this->assertSame(5, $counted->fresh()->violationQtyOn($date));

        $this->actingAs($user)->postJson(route('planner.routines.slip', $plain), ['date' => $date, 'quantity' => 5])
            ->assertOk();
        $this->assertSame(1, $plain->fresh()->violationQtyOn($date));
    }

    public function test_step_slip_keeps_other_slots_open(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid']);
        $morning = RoutineChecklistItem::create(['routine_id' => $routine->id, 'user_id' => $user->id, 'name' => 'Morning', 'sort_order' => 0, 'time_period' => 'morning']);
        RoutineChecklistItem::create(['routine_id' => $routine->id, 'user_id' => $user->id, 'name' => 'Night', 'sort_order' => 1, 'time_period' => 'night']);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('planner.check-items.slip', $morning), ['date' => $date])
            ->assertOk()
            ->assertJson(['ok' => true, 'step_qty' => 1, 'day_qty' => 1]);

        // Morning slot took the hit; the routine itself stays open on Night.
        $this->assertTrue($routine->fresh()->violatedOn($date));

        $html = $this->actingAs($user)->get('/planner?date='.$date)->assertOk()->getContent();
        $this->assertStringContainsString('لغزش ثبت شد', $html);
        $this->assertStringContainsString('Night', $html);
    }

    public function test_toggle_is_blocked_for_avoid(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid']);
        $item = RoutineChecklistItem::create(['routine_id' => $routine->id, 'user_id' => $user->id, 'name' => 'Morning', 'sort_order' => 0]);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('planner.routines.toggle', $routine), ['date' => $date])
            ->assertStatus(422);
        $this->actingAs($user)->postJson(route('planner.check-items.toggle', $item), ['date' => $date])
            ->assertStatus(422);
        $this->actingAs($user)->postJson(route('planner.routines.slip', Routine::factory()->create(['user_id' => User::factory()->create()->id])))
            ->assertForbidden();

        // Build routines still toggle fine.
        $build = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $this->actingAs($user)->postJson(route('planner.routines.toggle', $build), ['date' => $date])->assertOk();
    }

    public function test_craving_note_stored_with_timestamp(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid']);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('planner.routines.note', $routine), [
            'date' => $date,
            'kind' => 'craving',
            'occurred_at' => now()->format('Y-m-d H:i'),
            'note' => 'After lunch urge',
        ])->assertOk()->assertJson(['ok' => true, 'kind' => 'craving']);

        $note = RoutineNote::where('routine_id', $routine->id)->firstOrFail();
        $this->assertSame('craving', $note->kind);
        $this->assertSame('After lunch urge', $note->note);
        $this->assertNotNull($note->occurred_at);
    }

    public function test_avoid_metrics_clean_streak_and_last7(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'daily', 'behavior_type' => 'avoid',
            'created_at' => now()->subDays(5),
        ]);
        $yesterday = now()->subDay()->toDateString();
        RoutineViolation::create([
            'routine_id' => $routine->id, 'user_id' => $user->id,
            'occurred_at' => now()->subDay(), 'occurred_date' => $yesterday, 'quantity' => 1,
        ]);

        $m = $routine->fresh()->avoidMetrics(now()->startOfDay());
        $this->assertSame(0, $m['current']);
        $this->assertGreaterThanOrEqual(1, $m['best']);

        $states = collect($m['last7'])->pluck('state', 'date')->all();
        $this->assertSame('violated', $states[$yesterday]);

        // Planner + hub render the avoid card with no checkbox.
        $html = $this->actingAs($user)->get('/planner')->assertOk()->getContent();
        $this->assertStringContainsString('ترک‌کردنی', $html);

        $hub = $this->actingAs($user)->get(route('routines.index'))->assertOk()->getContent();
        $this->assertStringContainsString('ترک‌کردنی', $hub);
    }
}
