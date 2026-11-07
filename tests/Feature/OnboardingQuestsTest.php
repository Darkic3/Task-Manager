<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingQuestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_priorities_and_progress_snapshot(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.priorities'), [
            'tracks' => ['habit', 'do'],
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame(['habit', 'do'], $user->onboarding_state['priorities']);

        $snap = app(\App\Services\Onboarding\OnboardingQuestService::class)->progressForUser($user);
        $this->assertFalse($snap['needs_priorities']);
        $this->assertSame('routines', $snap['active']);
        $this->assertNotEmpty($snap['modules']['planner']['steps']);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('ob-journey', $html);
        $this->assertStringContainsString('obTrackModal', $html);
    }

    public function test_seed_task_template_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.seed'), ['kind' => 'task'])->assertRedirect();
        $this->assertSame(1, $user->tasks()->count());

        // Second call: already has data → no duplicate.
        $this->actingAs($user)->post(route('onboarding.seed'), ['kind' => 'task'])->assertRedirect();
        $this->assertSame(1, $user->tasks()->count());

        $snap = app(\App\Services\Onboarding\OnboardingQuestService::class)->progressForUser($user->fresh());
        $this->assertTrue($snap['modules']['tasks']['done'] >= 1);
    }

    public function test_planner_renders_quest_bar(): void
    {
        $user = User::factory()->create();
        $html = $this->actingAs($user)->get(route('planner.index'))->assertOk()->getContent();
        $this->assertStringContainsString('ob-quest', $html);
    }
}
