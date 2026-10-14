<?php

namespace Tests\Feature;

use App\Models\DayLog;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayLogTest extends TestCase
{
    use RefreshDatabase;

    private function activityPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => today()->toDateString(),
            'kind' => 'activity',
            'title' => 'Playing piano',
            'note' => null,
            'period' => 'evening',
            'duration_seconds' => 2700,
            'amount' => null,
            'amount_unit' => null,
            'start_time' => null,
            'end_time' => null,
            'logged_at' => '20:20',
            'mood' => null,
            'mood_picked' => null,
            'is_quit' => false,
            'time_entry_id' => null,
        ], $overrides);
    }

    public function test_guest_cannot_access_day_logs(): void
    {
        $this->postJson(route('day-logs.store'), ['title' => 'Nope'])->assertUnauthorized();
        $this->getJson(route('day-logs.index'))->assertUnauthorized();
    }

    public function test_index_returns_only_the_requested_day_plus_catalog(): void
    {
        $user = User::factory()->create();
        DayLog::factory()->create(['user_id' => $user->id, 'date' => today(), 'title' => 'Today one']);
        DayLog::factory()->create(['user_id' => $user->id, 'date' => today()->subDay(), 'title' => 'Yesterday one']);
        DayLog::factory()->create(['date' => today(), 'title' => 'Stranger log']);

        $response = $this->actingAs($user)->getJson(route('day-logs.index', ['date' => today()->toDateString()]));

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertCount(1, $response->json('entries'));
        $this->assertSame('Today one', $response->json('entries.0.title'));
        $this->assertSame('none', $response->json('entries.0.mode'));
        $this->assertNotEmpty($response->json('catalog'));
    }

    public function test_index_maps_modes_from_stored_fields(): void
    {
        $user = User::factory()->create();
        DayLog::factory()->create([
            'user_id' => $user->id, 'date' => today(),
            'start_time' => '19:30', 'end_time' => '20:15', 'period' => 'evening',
        ]);

        $response = $this->actingAs($user)->getJson(route('day-logs.index'));

        $response->assertOk();
        $this->assertSame('range', $response->json('entries.0.mode'));
        $this->assertSame('19:30', $response->json('entries.0.start_time'));
    }

    public function test_store_activity_with_duration(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('day-logs.store'), $this->activityPayload());

        $response->assertCreated()->assertJsonPath('ok', true);
        $this->assertSame('duration', $response->json('entry.mode'));
        $this->assertDatabaseHas('day_logs', [
            'user_id' => $user->id, 'title' => 'Playing piano', 'duration_seconds' => 2700,
        ]);
    }

    public function test_store_journal_requires_mood_and_rejects_time_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('day-logs.store'), [
            'date' => today()->toDateString(), 'kind' => 'journal', 'title' => 'A calm evening',
            'mood' => 6, 'mood_picked' => true, 'logged_at' => '22:30',
        ])->assertCreated();

        $this->actingAs($user)->postJson(route('day-logs.store'), [
            'date' => today()->toDateString(), 'kind' => 'journal', 'title' => 'No mood one',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson(route('day-logs.store'), $this->activityPayload([
            'kind' => 'journal', 'mood' => 6, 'period' => 'evening',
        ]))->assertUnprocessable();
    }

    public function test_store_validation_rejects_bad_input(): void
    {
        $user = User::factory()->create();
        $as = fn (array $payload) => $this->actingAs($user)->postJson(route('day-logs.store'), $payload)->assertUnprocessable();

        $as($this->activityPayload(['title' => '']));
        $as($this->activityPayload(['kind' => 'dance']));
        $as($this->activityPayload(['mood' => 5]));
        $as($this->activityPayload(['mood' => 11, 'kind' => 'journal']));
        $as($this->activityPayload(['logged_at' => '25:00']));
        $as($this->activityPayload(['period' => 'dawn']));
        $as($this->activityPayload(['date' => today()->addDay()->toDateString()]));
        $as($this->activityPayload(['time_entry_id' => 999999]));
        $as($this->activityPayload(['duration_seconds' => 0]));
    }

    public function test_update_title_and_logged_at(): void
    {
        $user = User::factory()->create();
        $log = DayLog::factory()->create(['user_id' => $user->id, 'title' => 'Old title']);

        $response = $this->actingAs($user)->patchJson(route('day-logs.update', $log), [
            'title' => 'New title', 'logged_at' => '21:05',
        ]);

        $response->assertOk()->assertJsonPath('entry.title', 'New title');
        $this->assertSame('21:05', $log->fresh()->logged_at);
    }

    public function test_update_rejects_kind_flip_that_keeps_time_fields(): void
    {
        $user = User::factory()->create();
        $log = DayLog::factory()->create(['user_id' => $user->id, 'period' => 'evening']);

        $this->actingAs($user)->patchJson(route('day-logs.update', $log), ['kind' => 'journal'])
            ->assertUnprocessable();
    }

    public function test_intruder_cannot_touch_others_logs(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $log = DayLog::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->patchJson(route('day-logs.update', $log), ['title' => 'Hacked'])
            ->assertForbidden();
        $this->actingAs($intruder)->deleteJson(route('day-logs.destroy', $log))
            ->assertForbidden();
        $this->assertSame($log->title, $log->fresh()->title);
    }

    public function test_destroy_removes_the_entry(): void
    {
        $user = User::factory()->create();
        $log = DayLog::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson(route('day-logs.destroy', $log))->assertOk();

        $this->assertDatabaseMissing('day_logs', ['id' => $log->id]);
    }

    public function test_merge_sums_seconds_into_earliest_row(): void
    {
        $user = User::factory()->create();
        $first = DayLog::factory()->create([
            'user_id' => $user->id, 'title' => 'Piano', 'duration_seconds' => 2700, 'note' => 'Scales',
        ]);
        $second = DayLog::factory()->create([
            'user_id' => $user->id, 'title' => 'piano', 'duration_seconds' => 1800, 'note' => 'New piece',
        ]);

        $response = $this->actingAs($user)->postJson(route('day-logs.merge'), ['ids' => [$first->id, $second->id]]);

        $response->assertOk()->assertJsonPath('survivor.id', $first->id);
        $this->assertSame(4500, $response->json('survivor.duration_seconds'));
        $this->assertStringContainsString('Scales', $response->json('survivor.note'));
        $this->assertStringContainsString('New piece', $response->json('survivor.note'));
        $this->assertDatabaseMissing('day_logs', ['id' => $second->id]);
    }

    public function test_merge_rejects_mixed_or_untimed_groups_and_foreign_ids(): void
    {
        $user = User::factory()->create();
        $intruder = User::factory()->create();
        $timed = DayLog::factory()->create(['user_id' => $user->id, 'title' => 'Piano', 'duration_seconds' => 900]);
        $other = DayLog::factory()->create(['user_id' => $user->id, 'title' => 'Guitar', 'duration_seconds' => 900]);
        $untimed = DayLog::factory()->create(['user_id' => $user->id, 'title' => 'Piano']);
        $foreign = DayLog::factory()->create(['user_id' => $intruder->id, 'title' => 'Piano', 'duration_seconds' => 900]);

        $this->actingAs($user)->postJson(route('day-logs.merge'), ['ids' => [$timed->id, $other->id]])
            ->assertUnprocessable();
        $this->actingAs($user)->postJson(route('day-logs.merge'), ['ids' => [$timed->id, $untimed->id]])
            ->assertUnprocessable();
        $this->actingAs($user)->postJson(route('day-logs.merge'), ['ids' => [$timed->id, $foreign->id]])
            ->assertForbidden();
    }

    public function test_candidates_surface_repeats_and_skip_existing_routines(): void
    {
        $user = User::factory()->create();
        foreach ([10, 9, 8] as $daysAgo) {
            DayLog::factory()->create([
                'user_id' => $user->id, 'title' => 'Piano', 'duration_seconds' => 900,
                'date' => today()->subDays($daysAgo),
            ]);
        }
        Routine::factory()->create(['user_id' => $user->id, 'title' => 'Piano']);
        DayLog::factory()->create([
            'user_id' => $user->id, 'title' => 'Smoking', 'duration_seconds' => 60,
            'is_quit' => true, 'date' => today()->subDays(2),
        ]);
        DayLog::factory()->create([
            'user_id' => $user->id, 'title' => 'Smoking', 'duration_seconds' => 60,
            'is_quit' => true, 'date' => today()->subDay(),
        ]);

        $response = $this->actingAs($user)->getJson(route('day-logs.candidates'));

        $response->assertOk();
        $this->assertEmpty($response->json('habit'));
        $this->assertSame('Smoking', $response->json('quit.0.name'));
        $this->assertSame(2, $response->json('quit.0.count'));
    }

    public function test_candidates_suggest_habit_after_three_repeats(): void
    {
        $user = User::factory()->create();
        foreach ([20, 15, 10] as $daysAgo) {
            DayLog::factory()->create([
                'user_id' => $user->id, 'title' => 'Evening walk', 'duration_seconds' => 1200,
                'date' => today()->subDays($daysAgo),
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('day-logs.candidates'));

        $response->assertOk()->assertJsonPath('habit.0.name', 'Evening walk');
        $this->assertEmpty($response->json('quit'));
    }
}
