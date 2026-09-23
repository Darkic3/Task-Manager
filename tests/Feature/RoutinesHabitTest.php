<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutinesHabitTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_run_against_the_dedicated_test_database(): void
    {
        $this->assertSame('task_test', DB::getDatabaseName());
    }

    public function test_habit_metrics_returns_rate_streak_and_last7(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        /* Backdate so past days count as occurrences */
        $routine->created_at = now()->subDays(10);
        $routine->save();

        /* Completed yesterday and two days ago, today unchecked */
        $routine->toggleOn(now()->subDays(1));
        $routine->toggleOn(now()->subDays(2));

        $m = $routine->habitMetrics(now()->startOfDay());

        $this->assertSame(2, $m['streak']);
        $this->assertGreaterThanOrEqual(0, $m['rate']);
        $this->assertCount(7, $m['last7']);
        /* tonight not yet counted; yesterday done, the day before missed */
        $states = collect($m['last7'])->pluck('state', 'date');
        $this->assertSame('done', $states[now()->subDay()->toDateString()]);
        $this->assertSame('today', $states[now()->toDateString()]);
        /* oldest of the 7 window: missed (occurred but not completed) */
        $this->assertSame('missed', $states[now()->subDays(6)->toDateString()]);
    }

    public function test_day_view_renders_habit_ring_with_rate_and_streak(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Morning review']);
        $routine->toggleOn(now()->subDays(1));
        $routine->toggleOn(now()->subDays(2));
        $routine->toggleOn(now()->subDays(3));

        $response = $this->actingAs($user)->get('/planner');

        $response->assertOk();
        $response->assertSee('habit-ring', false);
        $response->assertSee('ring-fg', false);
        $response->assertSee('Morning review');
        $response->assertSee('🔥', false);
        $response->assertSee('last7', false);
        $response->assertSee('sq-future', false);
    }

    public function test_toggle_is_idempotent_after_recheck(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $date = now()->subDay()->toDateString();

        foreach ([true, false, true, false, true] as $shouldComplete) {
            $result = $routine->toggleOn($date);
            $this->assertSame($shouldComplete, $result);
        }

        $this->assertSame(1, $routine->completions()->whereDate('completed_date', $date)->count());
    }

    public function test_archiving_routine_keeps_history_but_hides_it(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Study review']);
        $routine->toggleOn(now()->subDay());

        $this->actingAs($user)->delete(route('routines.destroy', $routine))
            ->assertRedirect();

        /* History rows survive the delete (archive) */
        $this->assertDatabaseHas('routine_completions', ['routine_id' => $routine->id]);
        $this->assertSoftDeleted('routines', ['id' => $routine->id]);

        /* Archived routine leaves the hub list */
        $response = $this->actingAs($user)->get(route('routines.index'));
        $response->assertOk();
        $response->assertDontSee('Study review');
        $response->assertSee('Weekly', false);
    }

    public function test_frequency_pages_redirect_to_hub_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('routines.showDaily'))
            ->assertRedirect(route('routines.index', ['filter' => 'daily']));
        $this->actingAs($user)->get(route('routines.showWeekly'))
            ->assertRedirect(route('routines.index', ['filter' => 'weekly']));
        $this->actingAs($user)->get(route('routines.showMonthly'))
            ->assertRedirect(route('routines.index', ['filter' => 'monthly']));
    }

    public function test_routine_hub_renders_minimal_with_score_and_filter(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'title' => 'Morning review']);
        $routine->created_at = now()->subDays(3);
        $routine->save();
        $routine->toggleOn(now()->subDay());

        $response = $this->actingAs($user)->get(route('routines.index'));

        $response->assertOk();
        $response->assertSee('Weekly', false);
        $response->assertSee('rh-chip', false);
        $response->assertSee('rh-score-bar', false);
        $response->assertSee('Morning review');
        $response->assertSee('🔥', false);
        $response->assertSee('last7', false);
        $response->assertDontSee('content-header', false);
        $response->assertDontSee('cu-kanban', false);
        $response->assertDontSee('Gradient', false);
    }

    public function test_dashboard_renders_today_routines_widget_with_counts(): void
    {
        $user = User::factory()->create();
        $a = Routine::factory()->create(['user_id' => $user->id, 'title' => 'Routine Alpha']);
        Routine::factory()->create(['user_id' => $user->id, 'title' => 'Routine Beta']);
        $a->toggleOn(now());

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('id="dbRoutineCount"', false);
        $response->assertSee('>1/2</span>', false);
        $response->assertSee('Routine Alpha');
        $response->assertSee('Routine Beta');
        $response->assertSee('dbToggleRoutine', false);
        $response->assertSee(route('planner.routines.toggle', $a), false);
    }

    /* ── every-N-days frequency ───────────────────────────── */

    public function test_every_other_day_routine_occurs_on_alternating_days(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create([
            'user_id' => $user->id, 'frequency' => 'every_n_days', 'every_n_days' => 2,
        ]);
        $routine->created_at = now()->subDays(4);
        $routine->save();

        /* anchored at day 0 (creation): days 0,2,4 occur — 1,3 rest */
        $this->assertTrue($routine->occursOn(now()->subDays(4)));
        $this->assertFalse($routine->occursOn(now()->subDays(3)));
        $this->assertTrue($routine->occursOn(now()->subDays(2)));
        $this->assertFalse($routine->occursOn(now()->subDays(1)));
        $this->assertTrue($routine->occursOn(now()));
        $this->assertTrue($routine->occursOn(now()->addDays(2)));
    }

    public function test_every_n_days_recurrence_label(): void
    {
        $user = User::factory()->create();
        $n2 = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'every_n_days', 'every_n_days' => 2]);
        $n3 = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'every_n_days', 'every_n_days' => 3]);

        $this->assertSame('Every other day', $n2->recurrenceLabel());
        $this->assertSame('Every 3 days', $n3->recurrenceLabel());
    }

    public function test_store_accepts_every_n_days_frequency(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('routines.store'), [
            'title' => 'Cobra stretch',
            'frequency' => 'every_n_days',
            'every_n_days' => 2,
        ])->assertRedirect(route('routines.index'));

        $this->assertDatabaseHas('routines', [
            'title' => 'Cobra stretch',
            'frequency' => 'every_n_days',
            'every_n_days' => 2,
        ]);
    }

    public function test_every_n_days_requires_the_interval(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('routines.store'), [
            'title' => 'Broken', 'frequency' => 'every_n_days',
        ])->assertSessionHasErrors('every_n_days');
    }

    /* ── routine steps (sub-items) ────────────────────────── */

    public function test_steps_can_be_created_and_synced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('routines.store'), [
            'title' => 'Cobra pose',
            'frequency' => 'daily',
            'items' => [
                ['name' => 'Set 1 (3×15)'],
                ['name' => 'Set 2 (3×15)'],
                ['name' => 'Set 3 (3×15)'],
            ],
        ])->assertRedirect();

        $routine = Routine::where('title', 'Cobra pose')->firstOrFail();
        $this->assertCount(3, $routine->checklistItems);

        /* update: remove one, rename one, add one → net stays 3 with new names */
        $ids = $routine->checklistItems->pluck('id')->all();
        $this->actingAs($user)->put(route('routines.update', $routine), [
            'title' => 'Cobra pose',
            'frequency' => 'daily',
            'items' => [
                ['id' => $ids[0], 'name' => 'Set 1 (3×15)'],
                ['id' => $ids[2], 'name' => 'Set 2 (3×15) renamed'],
                ['name' => 'Set 3 (3×15)'],
            ],
        ])->assertRedirect();

        $routine->refresh()->load('checklistItems');
        $this->assertCount(3, $routine->checklistItems);
        $this->assertContains('Set 2 (3×15) renamed', $routine->checklistItems->pluck('name')->all());
    }

    public function test_completing_all_steps_completes_the_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily', 'title' => 'Cobra']);
        $s1 = \App\Models\RoutineChecklistItem::create(['routine_id' => $routine->id, 'user_id' => $user->id, 'name' => 'Set 1', 'sort_order' => 0]);
        $s2 = \App\Models\RoutineChecklistItem::create(['routine_id' => $routine->id, 'user_id' => $user->id, 'name' => 'Set 2', 'sort_order' => 1]);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('planner.check-items.toggle', $s1), ['date' => $date])
            ->assertOk()
            ->assertJson(['completed' => true, 'routine_completed' => false, 'steps_done' => 1, 'steps_total' => 2]);

        $this->actingAs($user)->postJson(route('planner.check-items.toggle', $s2), ['date' => $date])
            ->assertOk()
            ->assertJson(['completed' => true, 'routine_completed' => true, 'steps_done' => 2, 'steps_total' => 2]);

        $this->assertTrue($routine->completedOn($date));

        /* un-ticking a step un-completes the routine automatically */
        $this->actingAs($user)->postJson(route('planner.check-items.toggle', $s1), ['date' => $date])
            ->assertOk()
            ->assertJson(['completed' => false, 'routine_completed' => false, 'steps_done' => 1, 'steps_total' => 2]);

        $this->assertFalse($routine->fresh()->completedOn($date));
    }

    public function test_whole_routine_toggle_syncs_step_completions(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $s1 = \App\Models\RoutineChecklistItem::create(['routine_id' => $routine->id, 'user_id' => $user->id, 'name' => 'A', 'sort_order' => 0]);
        $s2 = \App\Models\RoutineChecklistItem::create(['routine_id' => $routine->id, 'user_id' => $user->id, 'name' => 'B', 'sort_order' => 1]);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson(route('planner.routines.toggle', $routine), ['date' => $date])
            ->assertOk()
            ->assertJson(['completed' => true, 'items' => [
                ['id' => $s1->id, 'completed' => true],
                ['id' => $s2->id, 'completed' => true],
            ]]);

        $this->assertTrue($s1->completedOn($date));
        $this->assertTrue($s2->completedOn($date));

        /* uncheck routine → steps cleared too */
        $this->actingAs($user)->postJson(route('planner.routines.toggle', $routine), ['date' => $date])
            ->assertOk();
        $this->assertFalse($s1->fresh()->completedOn($date));
        $this->assertFalse($s2->fresh()->completedOn($date));
    }

    public function test_day_page_renders_steps_and_hub_lists_frequency(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'every_n_days', 'every_n_days' => 2, 'title' => 'Cobra pose']);
        $routine->created_at = now()->subDays(2);
        $routine->save();
        \App\Models\RoutineChecklistItem::create(['routine_id' => $routine->id, 'user_id' => $user->id, 'name' => 'Set 1 (3×15)', 'sort_order' => 0]);

        $response = $this->actingAs($user)->get('/planner');
        $response->assertOk();
        $response->assertSee('Set 1 (3×15)');
        $response->assertSee('pl-steps', false);
        $response->assertSee('pl-step', false);
        $response->assertSee('toggleCheckItem', false);

        $hub = $this->actingAs($user)->get(route('routines.index'));
        $hub->assertOk();
        $hub->assertSee('Every N days', false);
        $hub->assertSee('Every other day');
    }
}
