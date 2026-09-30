<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MorningCheckinPickerTest extends TestCase
{
    use RefreshDatabase;

    private function wakeRoutine(User $user, array $overrides = []): Routine
    {
        return Routine::factory()->create(array_merge([
            'user_id' => $user->id,
            'frequency' => 'daily',
            'title' => 'Wake Up',
            'tracking_mode' => 'value',
            'value_kind' => 'time',
            'value_label' => 'Wake time',
            'time_period' => 'morning',
            'start_time' => '07:00',
        ], $overrides));
    }

    public function test_planner_row_renders_compact_picker_with_scheduled_default(): void
    {
        $user = User::factory()->create();
        $routine = $this->wakeRoutine($user);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'fa'])
            ->get(route('planner.index'));

        $response->assertOk();
        // Compact picker instance for this routine/day.
        $response->assertSee('tp-row-' . $routine->id, false);
        // Shared picker JS is loaded.
        $response->assertSee('TimePicker', false);
        // Scheduled 07:00 default rendered with Persian digits.
        $response->assertSee('۰۷:۰۰', false);
        // No native time input left for the routine log box.
        $response->assertDontSee('pl-time-field', false);
    }

    public function test_next_up_modal_uses_shared_picker(): void
    {
        $user = User::factory()->create();
        $this->wakeRoutine($user);

        $response = $this->actingAs($user)->get(route('planner.index'));

        $response->assertOk();
        $response->assertSee('id="plModalTime"', false);
        $response->assertSee('plModalTimeInput', false);
        // Old native modal input markup is gone.
        $response->assertDontSee('plModalTimePresets', false);
    }

    public function test_wake_time_logs_minutes_and_completes_routine(): void
    {
        $user = User::factory()->create();
        $routine = $this->wakeRoutine($user);

        $this->actingAs($user)->postJson(route('planner.routines.log', $routine), [
            'date' => now()->toDateString(),
            'value' => 450, // 07:30
        ])->assertOk()
          ->assertJsonPath('values.value', 450)
          ->assertJsonPath('routine_completed', true);
    }
}
