<?php

namespace Tests\Feature;

use App\Models\DayLog;
use App\Models\Law;
use App\Models\LawEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawDayLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeLaw(User $user, array $overrides = []): Law
    {
        return $user->laws()->create(array_merge([
            'title' => 'Test law',
            'coefficient' => 4,
            'starts_on' => today()->toDateString(),
            'status' => Law::STATUS_ACTIVE,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return [
            'date' => today()->toDateString(),
            'kind' => DayLog::KIND_ACTIVITY,
            'title' => 'Evening run',
        ] + $overrides;
    }

    public function test_store_with_law_creates_frozen_xp_event(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);

        $res = $this->actingAs($user)->postJson(route('day-logs.store'), $this->payload([
            'law_id' => $law->id,
            'law_direction' => -1,
        ]))->assertCreated();

        $entry = $res->json('entry');
        $this->assertEquals($law->id, $entry['law_id']);
        $this->assertEquals(-1, $entry['law_direction']);
        $this->assertEquals(-4, $entry['law_xp']);
        $this->assertEquals('Test law', $entry['law']['title']);

        $this->assertDatabaseHas('law_events', [
            'law_id' => $law->id,
            'direction' => -1,
            'xp_delta' => -4,
            'source' => LawEvent::SOURCE_MANUAL,
            'linkable_type' => DayLog::class,
        ]);
    }

    public function test_store_with_law_but_no_direction_fails_without_orphan(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);

        $this->actingAs($user)->postJson(route('day-logs.store'), $this->payload([
            'law_id' => $law->id,
        ]))->assertStatus(422)->assertJsonValidationErrors('law_direction');

        $this->assertEquals(0, DayLog::count());
        $this->assertEquals(0, LawEvent::count());
    }

    public function test_store_rejects_foreign_and_dead_laws(): void
    {
        $user = User::factory()->create();
        $strangerLaw = $this->makeLaw(User::factory()->create());
        $paused = $this->makeLaw($user, ['title' => 'Paused', 'status' => Law::STATUS_PAUSED]);
        $expired = $this->makeLaw($user, [
            'title' => 'Expired',
            'starts_on' => today()->subDays(5)->toDateString(),
            'ends_on' => today()->subDay()->toDateString(),
        ]);

        foreach ([$strangerLaw->id, $paused->id, $expired->id] as $lawId) {
            $this->actingAs($user)->postJson(route('day-logs.store'), $this->payload([
                'law_id' => $lawId,
                'law_direction' => 1,
            ]))->assertStatus(422)->assertJsonValidationErrors('law_id');
        }

        $this->assertEquals(0, LawEvent::count());
    }

    public function test_update_can_relink_change_direction_and_unlink(): void
    {
        $user = User::factory()->create();
        $lawA = $this->makeLaw($user);
        $lawB = $this->makeLaw($user, ['title' => 'Second', 'coefficient' => 7]);

        $log = $user->dayLogs()->create($this->payload());

        // Link to A, for-direction.
        $this->actingAs($user)->patchJson(route('day-logs.update', $log), [
            'law_id' => $lawA->id, 'law_direction' => 1,
        ])->assertOk();
        $this->assertEquals(1, LawEvent::count());
        $this->assertEquals(4, LawEvent::first()->xp_delta);

        // Flip direction → same single event, new delta.
        $this->actingAs($user)->patchJson(route('day-logs.update', $log), ['law_direction' => -1])->assertOk();
        $this->assertEquals(1, LawEvent::count());
        $this->assertEquals(-4, LawEvent::first()->xp_delta);

        // Move to B → old event replaced.
        $this->actingAs($user)->patchJson(route('day-logs.update', $log), [
            'law_id' => $lawB->id, 'law_direction' => 1,
        ])->assertOk();
        $this->assertEquals(1, LawEvent::count());
        $this->assertEquals($lawB->id, LawEvent::first()->law_id);
        $this->assertEquals(7, LawEvent::first()->xp_delta);

        // Unlink → event gone, entry keeps its text.
        $this->actingAs($user)->patchJson(route('day-logs.update', $log), ['law_id' => null])->assertOk();
        $this->assertEquals(0, LawEvent::count());
        $this->assertNull($log->fresh()->law_id);
        $this->assertSame('Evening run', $log->fresh()->title);
    }

    public function test_destroy_removes_linked_event(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $log = $user->dayLogs()->create($this->payload());
        app(\App\Services\LawEventService::class)->syncForDayLog($log, $law->id, 1);
        $this->assertEquals(1, LawEvent::count());

        $this->actingAs($user)->deleteJson(route('day-logs.destroy', $log))->assertOk();
        $this->assertEquals(0, LawEvent::count());
    }

    public function test_past_events_survive_coefficient_changes(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $log = $user->dayLogs()->create($this->payload());
        app(\App\Services\LawEventService::class)->syncForDayLog($log, $law->id, 1);

        $law->update(['coefficient' => 10]);

        $this->assertEquals(4, LawEvent::first()->xp_delta);
    }

    public function test_index_exposes_law_payload(): void
    {
        $user = User::factory()->create();
        $law = $this->makeLaw($user);
        $log = $user->dayLogs()->create($this->payload());
        app(\App\Services\LawEventService::class)->syncForDayLog($log, $law->id, 1);

        $res = $this->actingAs($user)->getJson(route('day-logs.index', ['date' => today()->toDateString()]))->assertOk();
        $entry = collect($res->json('entries'))->firstWhere('id', $log->id);

        $this->assertEquals($law->id, $entry['law']['id']);
        $this->assertEquals(4, $entry['law_xp']);
    }
}
