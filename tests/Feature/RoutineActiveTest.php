<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\RoutineCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineActiveTest extends TestCase
{
    use RefreshDatabase;

    private function routine(User $user, string $title = 'Morning run'): Routine
    {
        return Routine::factory()->create([
            'user_id' => $user->id,
            'frequency' => 'daily',
            'title' => $title,
        ]);
    }

    public function test_toggle_active_disables_and_reenables(): void
    {
        $user = User::factory()->create();
        $routine = $this->routine($user);

        $this->assertTrue($routine->isActive());

        $this->actingAs($user)->post(route('routines.toggle-active', $routine))
            ->assertRedirect(route('routines.index', ['show' => 'disabled']));

        $this->assertFalse($routine->fresh()->isActive());

        $this->actingAs($user)->post(route('routines.toggle-active', $routine))
            ->assertRedirect(route('routines.index'));

        $this->assertTrue($routine->fresh()->isActive());
    }

    public function test_disabled_routine_is_hidden_but_history_is_kept(): void
    {
        $user = User::factory()->create();
        $routine = $this->routine($user, 'Hidden habit xyz');
        $routine->toggleOn(now()->toDateString());

        $routine->update(['is_active' => false]);

        // Index hides it by default...
        $html = $this->actingAs($user)->get(route('routines.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Hidden habit xyz', $html);

        // ...but lists it in disabled mode.
        $html = $this->actingAs($user)->get(route('routines.index', ['show' => 'disabled']))->assertOk()->getContent();
        $this->assertStringContainsString('Hidden habit xyz', $html);

        // Track hides it too.
        $html = $this->actingAs($user)->get(route('track.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Hidden habit xyz', $html);

        // History is preserved: completion row + stats page still work.
        $this->assertSame(1, RoutineCompletion::where('routine_id', $routine->id)->count());
        $this->actingAs($user)->get(route('routines.stats', $routine))->assertOk();
    }

    public function test_planner_actions_reject_disabled_routines(): void
    {
        $user = User::factory()->create();
        $routine = $this->routine($user);
        $routine->update(['is_active' => false]);

        $this->actingAs($user)->postJson(route('planner.routines.toggle', $routine), ['date' => now()->toDateString()])
            ->assertNotFound();
        $this->actingAs($user)->postJson(route('planner.routines.skip', $routine), ['date' => now()->toDateString()])
            ->assertNotFound();
    }

    public function test_toggle_active_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $routine = $this->routine($owner);

        $this->actingAs($other)->post(route('routines.toggle-active', $routine))->assertForbidden();
        $this->assertTrue($routine->fresh()->isActive());
    }
}
