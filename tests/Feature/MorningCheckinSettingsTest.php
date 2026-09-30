<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MorningCheckinSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function timeRoutine(User $user, string $title = 'Wake Up'): Routine
    {
        return Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'daily',
            'title' => $title,
            'tracking_mode' => 'value',
            'value_kind' => 'time',
            'value_label' => 'Wake time',
        ]);
    }

    private function basePayload(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    public function test_can_enable_morning_checkin_with_routine_and_window(): void
    {
        $user = User::factory()->create();
        $routine = $this->timeRoutine($user);

        $this->actingAs($user)->put(route('profile.update'), array_merge($this->basePayload($user), [
            'morning_checkin_enabled' => '1',
            'wake_routine_id' => $routine->id,
            'morning_window_start' => '04:00',
            'morning_window_end' => '11:30',
        ]))->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertTrue($user->morning_checkin_enabled);
        $this->assertEquals($routine->id, $user->wake_routine_id);
        $this->assertSame('04:00', substr((string) $user->morning_window_start, 0, 5));
        $this->assertSame('11:30', substr((string) $user->morning_window_end, 0, 5));
    }

    public function test_enabling_without_routine_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.update'), array_merge($this->basePayload($user), [
            'morning_checkin_enabled' => '1',
        ]))->assertSessionHasErrors('wake_routine_id');

        $this->assertFalse($user->refresh()->morning_checkin_enabled);
    }

    public function test_foreign_routine_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $this->timeRoutine($other);

        $this->actingAs($user)->put(route('profile.update'), array_merge($this->basePayload($user), [
            'morning_checkin_enabled' => '1',
            'wake_routine_id' => $foreign->id,
        ]))->assertSessionHasErrors('wake_routine_id');
    }

    public function test_end_before_start_is_rejected(): void
    {
        $user = User::factory()->create();
        $routine = $this->timeRoutine($user);

        $this->actingAs($user)->put(route('profile.update'), array_merge($this->basePayload($user), [
            'morning_checkin_enabled' => '1',
            'wake_routine_id' => $routine->id,
            'morning_window_start' => '12:00',
            'morning_window_end' => '04:00',
        ]))->assertSessionHasErrors('morning_window_end');
    }

    public function test_edit_page_shows_morning_section_and_time_routines(): void
    {
        $user = User::factory()->create();
        $routine = $this->timeRoutine($user);

        $response = $this->actingAs($user)->get(route('profile.edit'));
        $response->assertOk();
        $response->assertSee('morning_checkin_enabled', false);
        $response->assertSee('wake_routine_id', false);
        $response->assertSee($routine->title, false);
    }
}
