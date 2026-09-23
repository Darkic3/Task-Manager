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

    public function test_toggle_returns_completed_false_when_unchecked(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'frequency' => 'daily']);
        $date = now()->toDateString();

        $this->actingAs($user)->postJson("/planner/routines/{$routine->id}/toggle", ['date' => $date])
            ->assertOk()->assertJson(['ok' => true, 'completed' => true]);

        $this->actingAs($user)->postJson("/planner/routines/{$routine->id}/toggle", ['date' => $date])
            ->assertOk()->assertJson(['ok' => true, 'completed' => false]);

        $this->assertDatabaseMissing('routine_completions', [
            'routine_id' => $routine->id,
            'completed_date' => $date,
        ]);
    }
}
