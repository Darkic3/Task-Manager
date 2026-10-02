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

    public function test_time_picker_display_and_modal_are_styled(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();

        // The shared picker's display must ship its own rules — the component
        // is used by the routine rows and the next-up modal.
        foreach ([
            '.tp-display',
            '.tp-digits',
            '.tp-sep',
            '.tp-cap',
            '.tp-step',
            '.tp-period',
            '.tp-chip',
            '.tp-presets',
            '.tp-compact .tp-display',
        ] as $selector) {
            $this->assertStringContainsString($selector, $html, "Missing picker style {$selector}");
        }

        // Routine detail modal surface.
        foreach ([
            '.pl-modal-dialog',
            '.pl-modal-head',
            '.pl-modal-title',
            '.pl-modal-x',
            '.pl-modal-body',
        ] as $selector) {
            $this->assertStringContainsString($selector, $html, "Missing modal style {$selector}");
        }

        // Both keyframes must be defined for the entrance motion to run.
        $this->assertStringContainsString('@keyframes plModalIn', $html);
        $this->assertStringContainsString('@keyframes plSheetIn', $html);
    }

    public function test_time_picker_keeps_its_control_markup(): void
    {
        $user = User::factory()->create();
        $this->wakeRoutine($user);

        $html = $this->actingAs($user)
            ->withSession(['locale' => 'fa'])
            ->get(route('planner.index'))
            ->assertOk()
            ->getContent();

        // Styling must not cost the picker any of its hooks: the JS binds
        // exclusively through these data attributes.
        foreach ([
            'data-tp',
            'data-tp-value',
            'data-tp-h',
            'data-tp-m',
            'data-tp-period',
            'data-tp-step="h:1"',
            'data-tp-step="h:-1"',
            'data-tp-step="m:1"',
            'data-tp-step="m:-1"',
            'data-tp-preset="now"',
            'data-tp-adjust="-15"',
            'data-tp-adjust="15"',
        ] as $hook) {
            $this->assertStringContainsString($hook, $html, "Missing picker hook {$hook}");
        }
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
