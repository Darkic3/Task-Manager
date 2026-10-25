<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiPlan;
use App\Models\AiSetting;
use App\Models\AiStudyBuild;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * Phase 2: the staged study-build workflow (A→E) on the persisted server
 * state — no new tables. Every gate is enforced server-side; the UI only
 * mirrors the stage returned by the show endpoint.
 */
class AiStudyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function startChain(User $user): array
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $result = StudyBuildOrchestrator::startFromOutline($user, null, $outline);
        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'start failed');

        return $result;
    }

    private function stage(User $user, AiStudyBuild $build): string
    {
        return $this->actingAs($user)
            ->getJson(route('ai.study-builds.show', $build))
            ->assertOk()->json('master.workflow_stage');
    }

    private function approveMaster(User $user, AiStudyBuild $build, int $version = 1)
    {
        return $this->actingAs($user)->postJson(
            route('ai.study-builds.approve', $build), ['version' => $version]
        );
    }

    private function approvePackages(User $user, AiStudyBuild $build, int $version = 1)
    {
        return $this->actingAs($user)->postJson(
            route('ai.study-builds.approve-packages', $build), ['version' => $version]
        );
    }

    private function withProvider(User $user): void
    {
        AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);
    }

    private function fakeRevisionModel(array $outline): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($outline, JSON_UNESCAPED_UNICODE)]]],
            ], 200),
        ]);
    }

    private function revisedOutline(): array
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $outline['book_title'] = 'روانشناسی رشد (ویرایش‌شده)';

        return [
            'book_title' => $outline['book_title'],
            'chapters' => array_map(fn ($ch) => [
                'title' => $ch['title'],
                'lessons' => array_map(fn ($ls) => [
                    'title' => $ls['title'],
                    'topics' => $ls['topics'],
                ], $ch['lessons']),
                'extra_tasks' => $ch['extra_tasks'],
            ], $outline['chapters']),
        ];
    }

    public function test_stages_advance_in_order(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $build = $started['build'];

        // A/B: summary is the proposed master — nothing executable.
        $this->assertSame('master_review', $this->stage($user, $build));

        $this->approveMaster($user, $build)->assertOk();
        $this->assertSame('packages_review', $this->stage($user, $build));

        $this->approvePackages($user, $build)->assertOk()
            ->assertJsonPath('master.packages_approved', true);
        $this->assertSame('preview_review', $this->stage($user, $build));

        // D: preview confirm creates nothing by itself.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))
            ->assertOk()->assertJsonPath('plan.status', 'confirmed');
        $this->assertSame('executing', $this->stage($user, $build));
        $this->assertSame(0, Project::where('user_id', $user->id)->count());

        // E: execution builds, then the next package is proposed (stage D).
        $res = $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true]
        )->assertOk()->assertJsonPath('plan.status', 'done');
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
        $this->assertNotNull($res->json('next_plan.id'));
        $this->assertSame('preview_review', $this->stage($user, $build));
    }

    public function test_packages_approval_needs_approved_master(): void
    {
        $user = User::factory()->create();
        $build = $this->startChain($user)['build'];

        $this->approvePackages($user, $build)
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');

        $this->assertFalse($build->fresh()->versions[0]['packages_approved'] ?? false);
        $this->assertSame('master_review', $this->stage($user, $build));
    }

    public function test_stale_and_unknown_package_versions_rejected(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $build = $this->startChain($user)['build'];
        $this->approveMaster($user, $build)->assertOk();
        $this->approvePackages($user, $build)->assertOk();
        $this->fakeRevisionModel($this->revisedOutline());
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertOk();

        $fresh = $build->fresh();
        $this->assertSame(2, $fresh->version);
        $this->assertSame('master_review', $this->stage($user, $fresh));

        // Approving packages for the retired version is rejected...
        $this->approvePackages($user, $fresh, 1)
            ->assertStatus(409)->assertJsonPath('code', 'superseded_version');
        // ...as is an unknown future version.
        $this->approvePackages($user, $fresh, 9)
            ->assertStatus(409)->assertJsonPath('code', 'unknown_version');

        // The new version needs its own approvals (B then C); old heads stay obsolete.
        $this->assertFalse(StudyBuildOrchestrator::packagesApproved($fresh));
        $this->approveMaster($user, $fresh, 2)->assertOk();
        $this->assertSame('packages_review', $this->stage($user, $fresh));
        $this->approvePackages($user, $fresh, 2)->assertOk()
            ->assertJsonPath('deduped', false);
        $this->assertSame('preview_review', $this->stage($user, $fresh));
    }

    public function test_package_confirm_rejected_before_master_approval(): void
    {
        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');

        $this->assertSame(AiPlan::STATUS_PROPOSED, $plan->fresh()->status);
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_execution_rejected_before_preview_confirm(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approveMaster($user, $started['build'])->assertOk();
        $this->approvePackages($user, $started['build'])->assertOk();

        // Stage D preview is still unconfirmed: execution is refused and
        // creates nothing (no silent success).
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'not_confirmed');

        $this->assertSame(AiPlan::STATUS_PROPOSED, $started['plan']->fresh()->status);
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_revise_blocked_after_package_confirm(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->approveMaster($user, $started['build'])->assertOk();
        $this->approvePackages($user, $started['build'])->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();

        $this->fakeRevisionModel($this->revisedOutline());
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $started['build']), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(422)->assertJsonPath('code', 'already_started');

        $this->assertSame(1, $started['build']->fresh()->version);
        $this->assertSame(AiPlan::STATUS_CONFIRMED, $started['plan']->fresh()->status);
    }

    public function test_successive_revises_keep_full_history(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $build = $this->startChain($user)['build'];
        $this->fakeRevisionModel($this->revisedOutline());

        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'اول']
        )->assertOk()->assertJsonPath('master.version', 2);
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build->fresh()), ['feedback' => 'دوم']
        )->assertOk()->assertJsonPath('master.version', 3);

        $versions = $build->fresh()->versions;
        $this->assertCount(3, $versions);
        $this->assertSame('روانشناسی رشد', $versions[0]['outline']['book_title']);
        $this->assertSame(
            ['superseded', 'superseded', 'proposed'],
            array_column($versions, 'status')
        );
        // Exactly one live head; the v1 head is obsolete. (The identical
        // second revision reuses the live head via idempotency instead of
        // stacking a duplicate — and must not retire the adopted head.)
        $this->assertSame(1, AiPlan::where('user_id', $user->id)->where('status', AiPlan::STATUS_PROPOSED)->count());
        $this->assertSame(1, AiPlan::where('user_id', $user->id)->where('status', AiPlan::STATUS_OBSOLETE)->count());
        $head = AiPlan::where('user_id', $user->id)->where('status', AiPlan::STATUS_PROPOSED)->firstOrFail();
        $this->assertSame(3, (int) $head->master_version);
    }

    public function test_conflicting_confirm_during_revise_loses_safely(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $build = $started['build'];
        $planId = $started['plan']->id;

        // A concurrent confirm landing mid-revision: the revision must fail
        // closed (already_started) and store nothing.
        Http::fake(function () use ($planId) {
            AiPlan::where('id', $planId)->update(['status' => AiPlan::STATUS_CONFIRMED]);

            return Http::response([
                'choices' => [['message' => ['content' => json_encode($this->revisedOutline(), JSON_UNESCAPED_UNICODE)]]],
            ], 200);
        });

        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(422)->assertJsonPath('code', 'already_started');

        $fresh = $build->fresh();
        $this->assertSame(1, $fresh->version);
        $this->assertCount(1, $fresh->versions);
        $this->assertSame('روانشناسی رشد', $fresh->versions[0]['outline']['book_title']);
        $this->assertSame(AiPlan::STATUS_CONFIRMED, AiPlan::findOrFail($planId)->status);
        $this->assertSame(0, AiPlan::where('status', AiPlan::STATUS_OBSOLETE)->count());
    }

    public function test_duplicate_operations_are_idempotent(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $build = $started['build'];
        $plan = $started['plan'];

        $this->approveMaster($user, $build)->assertOk()->assertJsonPath('deduped', false);
        $this->approveMaster($user, $build->fresh())->assertOk()->assertJsonPath('deduped', true);
        $this->approvePackages($user, $build->fresh())->assertOk()->assertJsonPath('deduped', false);
        $this->approvePackages($user, $build->fresh())->assertOk()->assertJsonPath('deduped', true);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertOk()->assertJsonMissingPath('deduped');
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertOk()->assertJsonPath('deduped', true);

        $done = $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $plan), ['run_all' => true]
        )->assertOk();
        $nextId = $done->json('next_plan.id');
        $this->assertNotNull($nextId);
        $tasks = Task::where('user_id', $user->id)->count();

        // Re-running the finished head resurfaces the same next package —
        // no duplicate book, no duplicate tasks.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertOk()->assertJsonPath('deduped', true)->assertJsonPath('next_plan.id', $nextId);
        $this->assertSame($tasks, Task::where('user_id', $user->id)->count());
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
    }

    public function test_failed_stage_change_stores_nothing_new(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $build = $started['build'];
        $this->approveMaster($user, $build)->assertOk();

        // Failed package-list approval (unknown version) changes nothing.
        $this->approvePackages($user, $build, 9)->assertStatus(409);
        $fresh = $build->fresh();
        $this->assertSame(1, $fresh->version);
        $this->assertSame(1, $fresh->approved_version);
        $this->assertFalse(StudyBuildOrchestrator::packagesApproved($fresh));
        $this->assertSame('packages_review', $this->stage($user, $fresh));

        // Failed master approval of an invalid outline stores nothing and
        // keeps the stage recoverable.
        $broken = AiStudyBuild::create([
            'user_id' => $user->id,
            'title' => 'broken',
            'outline_json' => ['book_title' => '', 'chapters' => []],
            'chunks_json' => [],
            'plan_ids' => [],
            'total_chunks' => 0,
            'done_chunks' => 0,
            'version' => 1,
            'status' => AiStudyBuild::STATUS_BUILDING,
            'expires_at' => now()->addDay(),
        ]);
        $this->approveMaster($user, $broken)->assertStatus(422);
        $this->assertNull($broken->fresh()->approved_version);
        $this->assertSame('master_review', $this->stage($user, $broken));
    }
}
