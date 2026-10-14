<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\Project;
use App\Models\User;
use App\Services\AiToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3, Step 3 — Plan lifecycle.
 *
 * Expired, cancelled, superseded and active plans are distinct; a stale
 * plan can never execute or be resurrected without fresh server-side
 * validation; replacement is an explicit user decision, never automatic.
 */
class AiPlanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(User $user, string $title = 'Build it'): AiPlan
    {
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => $title,
            'projects' => [['name' => 'P-'.$title, 'tasks' => [['title' => 'T1']]]],
        ], $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');

        return AiPlan::create([
            'user_id' => $user->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
    }

    /* ── supersede is an explicit decision with a live replacement ── */

    public function test_cancel_with_replacement_links_and_retires_old_plan(): void
    {
        $user = User::factory()->create();
        $old = $this->makePlan($user, 'Old plan');
        $new = $this->makePlan($user, 'New plan');

        $this->actingAs($user)
            ->postJson("/ai/plans/{$old->id}/cancel", ['replaced_by_plan_id' => $new->id])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $old->refresh();
        $this->assertSame(AiPlan::STATUS_CANCELLED, $old->status);
        $this->assertSame($new->id, (int) $old->superseded_by_plan_id);
        $this->assertTrue($old->isSuperseded());
        $this->assertSame(AiPlan::STATUS_PROPOSED, $new->fresh()->status, 'replacement stays live');
    }

    public function test_plain_cancel_leaves_no_link(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user);

        $this->actingAs($user)->postJson("/ai/plans/{$plan->id}/cancel")->assertOk();

        $plan->refresh();
        $this->assertSame(AiPlan::STATUS_CANCELLED, $plan->status);
        $this->assertNull($plan->superseded_by_plan_id);
        $this->assertFalse($plan->isSuperseded());
    }

    public function test_cancel_with_bad_replacement_changes_nothing(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user);
        $foreign = $this->makePlan(User::factory()->create(), 'Foreign');

        // Self-replacement.
        $this->actingAs($user)
            ->postJson("/ai/plans/{$plan->id}/cancel", ['replaced_by_plan_id' => $plan->id])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_replacement');

        // Someone else's plan.
        $this->actingAs($user)
            ->postJson("/ai/plans/{$plan->id}/cancel", ['replaced_by_plan_id' => $foreign->id])
            ->assertStatus(422);

        // Ghost id.
        $this->actingAs($user)
            ->postJson("/ai/plans/{$plan->id}/cancel", ['replaced_by_plan_id' => 999999])
            ->assertStatus(422);

        $plan->refresh();
        $this->assertSame(AiPlan::STATUS_PROPOSED, $plan->status);
        $this->assertNull($plan->superseded_by_plan_id);
    }

    /* ── superseded plans cannot confirm or execute ── */

    public function test_superseded_plan_rejects_structure_and_phase_with_own_code(): void
    {
        $user = User::factory()->create();
        $old = $this->makePlan($user, 'Old plan');
        $new = $this->makePlan($user, 'New plan');
        $this->actingAs($user)->postJson("/ai/plans/{$old->id}/cancel", ['replaced_by_plan_id' => $new->id])->assertOk();

        $this->actingAs($user)->postJson("/ai/plans/{$old->id}/confirm-structure")
            ->assertStatus(422)
            ->assertJsonPath('code', 'superseded')
            ->assertJsonPath('replaced_by', $new->id);

        $this->actingAs($user)->postJson("/ai/plans/{$old->id}/confirm-phase", ['phase' => 0])
            ->assertStatus(422)
            ->assertJsonPath('code', 'superseded');

        $this->assertSame(AiPlan::STATUS_CANCELLED, $old->fresh()->status);
        $this->assertSame(0, Project::where('user_id', $user->id)->count(), 'nothing may be created');
    }

    /* ── direct phase execution revalidates status, expiry, ownership ── */

    public function test_direct_phase_execution_on_stale_plan_fails_without_resurrection(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;

        $cancelled = $this->makePlan($user, 'Cancelled');
        $cancelled->update(['status' => AiPlan::STATUS_CANCELLED]);
        $res = $svc->executePlanPhase($cancelled, $user);
        $this->assertFalse($res['ok']);
        $this->assertSame(AiPlan::STATUS_CANCELLED, $cancelled->fresh()->status, 'cancelled must not resurrect');
        $this->assertSame(0, Project::where('user_id', $user->id)->count());

        $expired = $this->makePlan($user, 'Expired');
        $expired->update(['expires_at' => now()->subMinute()]);
        $res = $svc->executePlanPhase($expired, $user);
        $this->assertFalse($res['ok']);
        $this->assertSame(AiPlan::STATUS_PROPOSED, $expired->fresh()->status, 'expired must not resurrect to executing');

        $foreign = $this->makePlan(User::factory()->create(), 'Foreign');
        $res = $svc->executePlanPhase($foreign, $user);
        $this->assertFalse($res['ok']);
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
    }

    public function test_direct_phase_execution_on_live_plan_still_works(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user, 'Live');
        $plan->update(['status' => AiPlan::STATUS_CONFIRMED]);

        $res = (new AiToolService)->executePlanPhase($plan, $user);

        $this->assertTrue($res['ok'], $res['message'] ?? '');
        $this->assertSame(1, Project::where('user_id', $user->id)->count());
    }
}
