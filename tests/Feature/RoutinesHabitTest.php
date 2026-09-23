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
}
