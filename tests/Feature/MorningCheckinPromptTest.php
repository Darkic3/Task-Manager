<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MorningCheckinPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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
        ], $overrides));
    }

    private function enableCheckin(User $user, Routine $routine): void
    {
        $user->update([
            'morning_checkin_enabled' => true,
            'wake_routine_id' => $routine->id,
            'morning_window_start' => '04:00',
            'morning_window_end' => '12:00',
        ]);
    }

    private function travelMorning(string $time = '2026-09-30 08:15'): void
    {
        // 2026-09-30 is a Wednesday.
        Carbon::setTestNow(Carbon::parse($time, 'Asia/Tehran'));
    }

    public function test_modal_shows_in_morning_when_enabled_and_unlogged(): void
    {
        $this->travelMorning();
        $user = User::factory()->create();
        $routine = $this->wakeRoutine($user);
        $this->enableCheckin($user, $routine);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('id="mcModal"', false);
        $response->assertSee('id="mcTime"', false);
        $response->assertSee('morning-check-in', false);
        $response->assertSee(e($routine->title), false);
    }

    public function test_modal_renders_persian_content(): void
    {
        $this->travelMorning();
        $user = User::factory()->create(['locale' => 'fa']);
        $routine = $this->wakeRoutine($user);
        $this->enableCheckin($user, $routine);

        $response = $this->actingAs($user)
            ->withSession(['locale' => 'fa'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('id="mcModal"', false);
        foreach (['امروز ساعت چند بیدار شدی؟', 'ثبت بیداری', 'بعداً', 'امروز نه', 'صبح بخیر', 'هم‌اکنون'] as $text) {
            $response->assertSee($text, false);
        }
        // Raw English keys must not leak through.
        foreach (['What time did you wake up today?', 'Log Wake-up', 'Not today'] as $key) {
            $response->assertDontSee($key, false);
        }
    }

    public function test_modal_hidden_when_disabled(): void
    {
        $this->travelMorning();
        $user = User::factory()->create();
        $this->wakeRoutine($user);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="mcModal"', false);
    }

    public function test_modal_hidden_outside_window(): void
    {
        $this->travelMorning('2026-09-30 15:00');
        $user = User::factory()->create();
        $routine = $this->wakeRoutine($user);
        $this->enableCheckin($user, $routine);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="mcModal"', false);
    }

    public function test_modal_hidden_when_already_logged(): void
    {
        $this->travelMorning();
        $user = User::factory()->create();
        $routine = $this->wakeRoutine($user);
        $this->enableCheckin($user, $routine);

        $this->actingAs($user)->postJson(route('planner.routines.log', $routine), [
            'date' => now()->toDateString(),
            'value' => 420,
        ])->assertOk();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="mcModal"', false);
    }

    public function test_modal_hidden_when_routine_not_occurring_today(): void
    {
        $this->travelMorning(); // Wednesday
        $user = User::factory()->create();
        $routine = $this->wakeRoutine($user, ['frequency' => 'weekly', 'days' => ['saturday']]);
        $this->enableCheckin($user, $routine);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="mcModal"', false);
    }

    public function test_dismiss_today_hides_until_tomorrow(): void
    {
        $this->travelMorning();
        $user = User::factory()->create();
        $routine = $this->wakeRoutine($user);
        $this->enableCheckin($user, $routine);

        $this->actingAs($user)->get(route('dashboard'))->assertSee('id="mcModal"', false);

        $this->actingAs($user)->postJson(route('morning-checkin.dismiss'), ['mode' => 'today'])
            ->assertOk()->assertJsonPath('ok', true);

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('id="mcModal"', false);
    }

    public function test_snooze_hides_temporarily_then_returns(): void
    {
        $this->travelMorning('2026-09-30 08:15');
        $user = User::factory()->create();
        $routine = $this->wakeRoutine($user);
        $this->enableCheckin($user, $routine);

        $this->actingAs($user)->postJson(route('morning-checkin.dismiss'), ['mode' => 'snooze'])
            ->assertOk();

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('id="mcModal"', false);

        $this->travelMorning('2026-09-30 09:15'); // 60 min later, snooze expired
        $this->actingAs($user)->get(route('dashboard'))->assertSee('id="mcModal"', false);
    }

    public function test_dismiss_rejects_invalid_mode(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('morning-checkin.dismiss'), ['mode' => 'forever'])
            ->assertStatus(422);
    }
}
