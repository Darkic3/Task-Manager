<?php

namespace Tests\Feature;

use App\Models\Law;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LawBasicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_law(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post(route('laws.store'), [
            'title' => 'No phone after 11pm',
            'coefficient' => 5,
            'starts_on' => today()->toDateString(),
            'ends_on' => null,
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('laws', [
            'user_id' => $user->id,
            'title' => 'No phone after 11pm',
            'coefficient' => 5,
            'status' => Law::STATUS_ACTIVE,
        ]);
    }

    public function test_coefficient_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('laws.store'), [
            'title' => 'Bad law',
            'coefficient' => 99,
            'starts_on' => today()->toDateString(),
        ])->assertSessionHasErrors('coefficient');

        $this->assertDatabaseMissing('laws', ['title' => 'Bad law']);
    }

    public function test_pause_resume_archive_flow(): void
    {
        $user = User::factory()->create();
        $law = $user->laws()->create([
            'title' => 'Read daily',
            'coefficient' => 3,
            'starts_on' => today()->toDateString(),
            'status' => Law::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)->post(route('laws.pause', $law))->assertRedirect();
        $this->assertSame(Law::STATUS_PAUSED, $law->fresh()->status);

        $this->actingAs($user)->post(route('laws.resume', $law))->assertRedirect();
        $this->assertSame(Law::STATUS_ACTIVE, $law->fresh()->status);

        $this->actingAs($user)->post(route('laws.archive', $law))->assertRedirect();
        $this->assertSame(Law::STATUS_ARCHIVED, $law->fresh()->status);
    }

    public function test_expired_law_lands_in_expired_tab(): void
    {
        $user = User::factory()->create();
        $law = $user->laws()->create([
            'title' => 'One day challenge',
            'coefficient' => 2,
            'starts_on' => today()->subDays(2)->toDateString(),
            'ends_on' => today()->subDay()->toDateString(),
            'status' => Law::STATUS_ACTIVE,
        ]);

        $this->assertTrue($law->fresh()->isExpired());
        $this->assertSame(Law::STATUS_EXPIRED, $law->fresh()->effectiveStatus());

        $this->actingAs($user)->get(route('laws.index', ['tab' => 'expired']))
            ->assertOk()
            ->assertSee('One day challenge');

        $this->actingAs($user)->get(route('laws.index', ['tab' => 'active']))
            ->assertOk()
            ->assertDontSee('One day challenge');
    }

    public function test_law_ownership_is_enforced(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $law = $owner->laws()->create([
            'title' => 'Private law',
            'coefficient' => 1,
            'starts_on' => today()->toDateString(),
            'status' => Law::STATUS_ACTIVE,
        ]);

        $this->actingAs($intruder)->get(route('laws.show', $law))->assertForbidden();
    }
}
