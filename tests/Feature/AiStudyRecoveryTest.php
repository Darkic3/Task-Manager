<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiPlan;
use App\Models\Project;
use App\Models\User;
use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * Phase 3: recovery contract — opening a conversation (refresh, reconnect)
 * rebuilds every staged card purely from server records. The endpoint must
 * expose the full stage, approvals, per-package actions and the head plan
 * preview at every workflow stage.
 */
class AiStudyRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function startChain(User $user, AiConversation $conv, ?string $fixture = null): array
    {
        $text = $fixture ?? StudyOutlineFixture::cognitiveRegressionExcerpt();
        $outline = StudyOutlineParser::parse($text);
        $result = StudyBuildOrchestrator::startFromOutline($user, $conv->id, $outline);
        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'start failed');

        return $result;
    }

    private function recover(User $user, AiConversation $conv)
    {
        return $this->actingAs($user)->getJson(
            route('ai.conversations.study-builds', $conv)
        );
    }

    private function approveAll(User $user, $build, int $version = 1): void
    {
        $this->actingAs($user)->postJson(
            route('ai.study-builds.approve', $build), ['version' => $version]
        )->assertOk();
        $this->actingAs($user)->postJson(
            route('ai.study-builds.approve-packages', $build), ['version' => $version]
        )->assertOk();
    }

    public function test_recovery_at_master_review(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $started = $this->startChain($user, $conv);

        $payload = $this->recover($user, $conv)->assertOk()->json('builds');
        $this->assertCount(1, $payload);
        $build = $payload[0];

        $this->assertSame('master_review', $build['master']['workflow_stage']);
        $this->assertFalse($build['master']['packages_approved']);
        $this->assertTrue($build['master']['validation']['ok']);
        foreach ($build['packages'] as $pkg) {
            $this->assertSame(['view'], $pkg['actions']);
        }
        $head = collect($build['packages'])->firstWhere('is_head', true);
        $this->assertNotNull($head['plan']);
        $this->assertFalse($head['plan']['chain']['master_approved']);
        $this->assertFalse($head['plan']['chain']['packages_approved']);
    }

    public function test_recovery_at_packages_review(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $started = $this->startChain($user, $conv);
        $this->actingAs($user)->postJson(
            route('ai.study-builds.approve', $started['build']), ['version' => 1]
        )->assertOk();

        $payload = $this->recover($user, $conv)->assertOk()->json('builds');
        $build = $payload[0];

        $this->assertSame('packages_review', $build['master']['workflow_stage']);
        $this->assertTrue($build['master']['validation']['ok']);
        foreach ($build['packages'] as $pkg) {
            $this->assertSame(['view'], $pkg['actions']);
        }
        // Head preview exists but carries locked gates.
        $head = collect($build['packages'])->firstWhere('is_head', true);
        $this->assertNotNull($head['plan']);
        $this->assertFalse($head['plan']['chain']['packages_approved']);
    }

    public function test_recovery_at_preview(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $started = $this->startChain($user, $conv);
        $this->approveAll($user, $started['build']);

        $payload = $this->recover($user, $conv)->assertOk()->json('builds');
        $build = $payload[0];

        $this->assertSame('preview_review', $build['master']['workflow_stage']);
        $this->assertTrue($build['master']['packages_approved']);
        $head = collect($build['packages'])->firstWhere('is_head', true);
        $this->assertContains('confirm', $head['actions']);
        $this->assertContains('execute', $head['actions']);
        $this->assertTrue($head['plan']['chain']['master_approved']);
        $this->assertTrue($head['plan']['chain']['packages_approved']);
        $this->assertSame(AiPlan::STATUS_PROPOSED, $head['plan']['status']);
    }

    public function test_recovery_while_executing(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $started = $this->startChain($user, $conv);
        $this->approveAll($user, $started['build']);
        $this->actingAs($user)->postJson(
            route('ai.plans.confirm-structure', $started['plan'])
        )->assertOk();

        $payload = $this->recover($user, $conv)->assertOk()->json('builds');
        $build = $payload[0];

        $this->assertSame('executing', $build['master']['workflow_stage']);
        $head = collect($build['packages'])->firstWhere('is_head', true);
        $this->assertSame(AiPlan::STATUS_CONFIRMED, $head['plan']['status']);
        $this->assertContains('execute', $head['actions']);
    }

    public function test_recovery_when_failed(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $started = $this->startChain($user, $conv);
        $this->approveAll($user, $started['build']);
        $this->actingAs($user)->postJson(
            route('ai.plans.confirm-structure', $started['plan'])
        )->assertOk();
        $done = $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true]
        )->assertOk();
        $pkg2 = AiPlan::findOrFail($done->json('next_plan.id'));

        // Break the attach target mid-chain: the package fails honestly.
        Project::where('user_id', $user->id)->delete();
        $this->actingAs($user)->postJson(
            route('ai.plans.confirm-structure', $pkg2)
        )->assertOk();
        $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $pkg2), ['run_all' => true]
        )->assertStatus(422)->assertJsonPath('code', 'phase_failed');

        $payload = $this->recover($user, $conv)->assertOk()->json('builds');
        $build = $payload[0];

        $this->assertSame('failed', $build['master']['workflow_stage']);
        $head = collect($build['packages'])->firstWhere('is_head', true);
        $this->assertSame(AiPlan::STATUS_FAILED, $head['plan']['status']);
        $this->assertNotEmpty($head['plan']['last_error']);
        $this->assertContains('execute', $head['actions'], 'failed head must offer retry');
    }

    public function test_recovery_when_done(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $started = $this->startChain($user, $conv);
        $this->approveAll($user, $started['build']);

        $planId = $started['plan']->id;
        for ($i = 0; $i < 3 && $planId !== null; $i++) {
            $plan = AiPlan::findOrFail($planId);
            if ($plan->status === AiPlan::STATUS_PROPOSED) {
                $this->actingAs($user)->postJson(
                    route('ai.plans.confirm-structure', $plan)
                )->assertOk();
            }
            $res = $this->actingAs($user)->postJson(
                route('ai.plans.confirm-phase', $plan), ['run_all' => true]
            )->assertOk();
            $planId = $res->json('next_plan.id');
        }

        $payload = $this->recover($user, $conv)->assertOk()->json('builds');
        $build = $payload[0];

        $this->assertSame('done', $build['master']['workflow_stage']);
        $this->assertSame('done', $build['study_build']['status']);
        foreach ($build['packages'] as $pkg) {
            $this->assertSame('done', $pkg['status']);
            $this->assertSame(['view'], $pkg['actions'], 'terminal packages expose no actions');
        }
    }

    public function test_obsolete_heads_stay_view_only(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $started = $this->startChain($user, $conv);

        // Real master revision: the v1 head is retired as obsolete.
        \App\Models\AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::cognitiveRegressionExcerpt());
        $outline['book_title'] = 'کتاب روانشناسی شناختی (ویرایش‌شده)';
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response([
            'choices' => [['message' => ['content' => json_encode($outline, JSON_UNESCAPED_UNICODE)]]],
        ], 200)]);
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $started['build']), ['feedback' => 'عنوان را عوض کن']
        )->assertOk()->assertJsonPath('master.version', 2);

        $payload = $this->recover($user, $conv)->assertOk()->json('builds');
        $packages = $payload[0]['packages'];
        $old = collect($packages)->firstWhere('master_version', 1);

        $this->assertNotNull($old);
        $this->assertFalse($old['is_current_version']);
        $this->assertSame(AiPlan::STATUS_OBSOLETE, $old['status']);
        $this->assertSame(['view'], $old['actions']);
        $this->assertSame('master_review', $payload[0]['master']['workflow_stage']);
    }

    public function test_recovery_empty_and_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $owner->id, 'label' => 'c']);

        $this->recover($owner, $conv)->assertOk()->assertJsonPath('builds', []);
        $this->actingAs($other)->getJson(
            route('ai.conversations.study-builds', $conv)
        )->assertForbidden();
    }
}
