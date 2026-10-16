<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutinePostponeTest extends TestCase
{
    use RefreshDatabase;

    private function everyOtherDay(User $user, int $createdDaysAgo = 4): Routine
    {
        $routine = Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'every_n_days',
            'every_n_days' => 2,
        ]);
        $routine->created_at = now()->subDays($createdDaysAgo)->startOfDay();
        $routine->save();

        return $routine;
    }

    public function test_postponing_every_other_day_reanchors_from_tomorrow_and_skips_today(): void
    {
        $user = User::factory()->create();
        $routine = $this->everyOtherDay($user); // created 4 days ago -> today is an occurrence

        $this->assertTrue($routine->occursOn(now()));

        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->toDateString()])
            ->assertOk()
            ->assertJson(['ok' => true, 'skipped' => true, 'moved_to' => now()->addDay()->toDateString()]);

        $routine->refresh();

        /* Today is recorded as skipped with the postponed reason. */
        $this->assertDatabaseHas('routine_completions', [
            'routine_id' => $routine->id,
            'completed_date' => now()->toDateString(),
            'status' => 'skipped',
            'skip_reason' => 'postponed',
        ]);

        /* New anchor: tomorrow, then every other day. */
        $this->assertTrue($routine->occursOn(now()->addDay()));
        $this->assertFalse($routine->occursOn(now()->addDays(2)));
        $this->assertTrue($routine->occursOn(now()->addDays(3)));
        $this->assertFalse($routine->occursOn(now()->addDays(4)));
    }

    public function test_postponing_keeps_history_before_tomorrow_on_the_old_schedule(): void
    {
        $user = User::factory()->create();
        $routine = $this->everyOtherDay($user); // occurrences: -4, -2, 0, +2 …

        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->toDateString()])
            ->assertOk();

        $routine->refresh();

        /* Old days stay exactly as they were. */
        $this->assertTrue($routine->occursOn(now()->subDays(4)));
        $this->assertFalse($routine->occursOn(now()->subDays(3)));
        $this->assertTrue($routine->occursOn(now()->subDays(2)));
        $this->assertFalse($routine->occursOn(now()->subDays(1)));
        $this->assertTrue($routine->occursOn(now()));

        /* Scheduled keys across a window keep the pre-shift pattern in the past. */
        $keys = $routine->occurrenceDates(now()->subDays(4), now()->addDays(3));
        $this->assertContains(now()->subDays(4)->toDateString(), $keys);
        $this->assertContains(now()->subDays(2)->toDateString(), $keys);
        $this->assertContains(now()->addDay()->toDateString(), $keys);
        $this->assertContains(now()->addDays(3)->toDateString(), $keys);
        $this->assertNotContains(now()->addDays(2)->toDateString(), $keys);
    }

    public function test_postponing_a_weekly_routine_shifts_every_weekday_one_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-17 09:00:00')); // Saturday

        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'weekly',
            'days' => ['saturday'],
        ]);

        $this->assertTrue($routine->occursOn(now()));

        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->toDateString()])
            ->assertOk()
            ->assertJson(['moved_to' => '2026-10-18']);

        $routine->refresh();

        /* From tomorrow the routine runs on Sunday instead of Saturday. */
        $this->assertSame(['sunday'], $routine->effectiveDaysFor(now()->addDay()));
        $this->assertTrue($routine->occursOn(now()->addDay()));      // Sunday
        $this->assertFalse($routine->occursOn(now()->addDays(7)));   // next Saturday gone
        $this->assertTrue($routine->occursOn(now()->addDays(8)));    // next Sunday

        /* Past Saturdays keep occurring. */
        $this->assertTrue($routine->occursOn(now()->subDays(7)));
    }

    public function test_postpone_rejects_daily_and_monthly_routines(): void
    {
        $user = User::factory()->create();
        $daily = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $monthly = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'monthly', 'month_days' => [now()->day],
        ]);

        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $daily), ['date' => now()->toDateString()])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $monthly), ['date' => now()->toDateString()])
            ->assertStatus(422);
    }

    public function test_postpone_rejects_future_date_non_occurrence_and_completed_day(): void
    {
        $user = User::factory()->create();
        $routine = $this->everyOtherDay($user);

        /* Future date. */
        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->addDays(10)->toDateString()])
            ->assertStatus(422);

        /* A non-occurrence day (odd offset from the anchor). */
        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->subDay()->toDateString()])
            ->assertStatus(422);

        /* A day already completed. */
        $routine->toggleOn(now());
        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->toDateString()])
            ->assertStatus(422);
    }

    public function test_postpone_rejects_a_foreign_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = $this->everyOtherDay($owner);

        $this->actingAs($intruder)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->toDateString()])
            ->assertStatus(403);
    }

    public function test_day_view_shows_the_postpone_button_only_for_interval_and_weekly(): void
    {
        $user = User::factory()->create();
        $interval = $this->everyOtherDay($user);
        $interval->update(['title' => 'Interval move']);
        $daily = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Daily fixed']);

        $html = $this->actingAs($user)
            ->get(route('planner.index', ['view' => 'day', 'date' => now()->toDateString()]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('planner.routines.postpone', $interval), $html);
        $this->assertStringContainsString('postponeRoutine', $html);
        /* The button must render a real Bootstrap Icons class (an unknown class
           would silently draw nothing) plus a visible "tomorrow" label. */
        $this->assertStringContainsString('bi bi-arrow-right-short', $html);
        $this->assertStringContainsString('pl-routine-postpone', $html);
        $this->assertStringContainsString(__('Tomorrow'), $html);
        $this->assertStringNotContainsString(route('planner.routines.postpone', $daily), $html);
    }

    public function test_editing_the_recurrence_clears_the_postpone_chain(): void
    {
        $user = User::factory()->create();
        $routine = $this->everyOtherDay($user);

        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->toDateString()])
            ->assertOk();

        $this->assertNotNull($routine->fresh()->schedule_shifts);

        $this->actingAs($user)->put(route('routines.update', $routine), [
            'title' => 'Interval move',
            'frequency' => 'every_n_days',
            'every_n_days' => 3,
        ])->assertRedirect(route('routines.index'));

        $this->assertNull($routine->fresh()->schedule_shifts);
    }

    public function test_new_cycle_does_not_copy_the_postpone_chain(): void
    {
        $user = User::factory()->create();
        $routine = $this->everyOtherDay($user);

        $this->actingAs($user)
            ->postJson(route('planner.routines.postpone', $routine), ['date' => now()->toDateString()])
            ->assertOk();

        $this->actingAs($user)
            ->post(route('routines.new-cycle', $routine))
            ->assertRedirect();

        $copy = Routine::where('parent_id', $routine->id)->firstOrFail();
        $this->assertNull($copy->schedule_shifts);
    }
}
