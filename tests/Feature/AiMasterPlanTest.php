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
 * P1 Master Plan: versioned structure + explicit approval as the gate for
 * all package execution. No provider, no real HTTP — revision calls are
 * faked at the Http layer.
 */
class AiMasterPlanTest extends TestCase
{
    use RefreshDatabase;

    private function startChain(User $user, ?int $conversationId = null): array
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $result = StudyBuildOrchestrator::startFromOutline($user, $conversationId, $outline);
        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'start failed');

        return $result;
    }

    private function approve(User $user, AiStudyBuild $build, int $version = 1)
    {
        return $this->actingAs($user)->postJson(
            route('ai.study-builds.approve', $build), ['version' => $version]
        );
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
        $outline['chapters'][0]['title'] = 'فصل ۱: درآمدی بر روانشناسی رشد';

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

    private function withProvider(User $user): void
    {
        AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);
    }

    public function test_master_proposed_on_chain_start_with_history(): void
    {
        $user = User::factory()->create();
        $result = $this->startChain($user);
        $build = $result['build'];

        $this->assertSame(1, $build->version);
        $this->assertNull($build->approved_version);
        $this->assertSame(AiStudyBuild::STATUS_BUILDING, $build->status);
        $versions = $build->versions;
        $this->assertCount(1, $versions);
        $this->assertSame('proposed', $versions[0]['status']);
        $this->assertSame(16, count($versions[0]['outline']['chapters']));

        // Proposal only: no operational data exists.
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_no_package_runs_before_master_approval(): void
    {
        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');

        $this->assertSame(AiPlan::STATUS_PROPOSED, $plan->fresh()->status);
        $this->assertNull($plan->fresh()->executed_at);
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_approve_valid_version_unlocks_packages(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);

        $this->approve($user, $started['build'])
            ->assertOk()
            ->assertJsonPath('master.approved_version', 1)
            ->assertJsonPath('master.version', 1);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
    }

    public function test_revise_creates_new_version_preserving_old(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->fakeRevisionModel($this->revisedOutline());

        $res = $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $started['build']), ['feedback' => 'عنوان کتاب را عوض کن']
        )->assertOk();

        $res->assertJsonPath('master.version', 2)
            ->assertJsonPath('master.approved_version', null);
        $versions = $res->json('master.versions');
        $this->assertCount(2, $versions);
        $this->assertSame('superseded', $versions[0]['status']);
        $this->assertSame('proposed', $versions[1]['status']);
        // v1 outline preserved verbatim for audit.
        $this->assertSame('روانشناسی رشد', $versions[0]['outline']['book_title']);
        $this->assertSame(16, count($versions[0]['outline']['chapters']));

        // Old unconfirmed head retired, new head proposed from v2.
        $this->assertSame(AiPlan::STATUS_CANCELLED, $started['plan']->fresh()->status);
        $newHeadId = $res->json('plan.id');
        $this->assertNotNull($newHeadId);
        $this->assertSame(AiPlan::STATUS_PROPOSED, AiPlan::findOrFail($newHeadId)->status);

        // Nothing executed during revision.
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
    }

    public function test_old_version_rejected_after_revision(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->fakeRevisionModel($this->revisedOutline());
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $started['build']), ['feedback' => 'عنوان را عوض کن']
        )->assertOk();

        // Approving the retired version fails; the new one works.
        $this->actingAs($user)->postJson(route('ai.study-builds.approve', $started['build']), ['version' => 1])
            ->assertStatus(409)->assertJsonPath('code', 'superseded_version');
        $this->approve($user, $started['build']->fresh(), 2)->assertOk()
            ->assertJsonPath('master.approved_version', 2);

        // The retired head can never execute.
        $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true]
        )->assertStatus(422);
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_duplicate_approve_is_deduped(): void
    {
        $user = User::factory()->create();
        $build = $this->startChain($user)['build'];

        $this->approve($user, $build)->assertOk()->assertJsonPath('deduped', false);
        $this->approve($user, $build->fresh())->assertOk()->assertJsonPath('deduped', true);

        $this->assertSame(1, $build->fresh()->version);
        $this->assertSame(1, $build->fresh()->approved_version);
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
    }

    public function test_invalid_structure_cannot_be_approved(): void
    {
        $user = User::factory()->create();
        $build = AiStudyBuild::create([
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

        $this->actingAs($user)->postJson(route('ai.study-builds.approve', $build), ['version' => 1])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_structure');

        $this->assertNull($build->fresh()->approved_version);
    }

    public function test_master_endpoints_enforce_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $build = $this->startChain($owner)['build'];

        $this->actingAs($other)->getJson(route('ai.study-builds.show', $build))->assertForbidden();
        $this->actingAs($other)->postJson(route('ai.study-builds.approve', $build), ['version' => 1])->assertForbidden();
        $this->actingAs($other)->postJson(route('ai.study-builds.revise', $build), ['feedback' => 'x'])->assertForbidden();

        $this->assertSame(1, $build->fresh()->version);
        $this->assertNull($build->fresh()->approved_version);
    }

    public function test_provider_failure_stores_nothing_and_retry_works(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $build = $this->startChain($user)['build'];

        // One fake for both attempts (a second Http::fake would only stack
        // behind the first '*' stub): fail first, succeed on retry.
        $failUpstream = true;
        $revJson = json_encode($this->revisedOutline(), JSON_UNESCAPED_UNICODE);
        Http::fake(function () use (&$failUpstream, $revJson) {
            if ($failUpstream) {
                return Http::response('upstream boom', 500);
            }

            return Http::response(['choices' => [['message' => ['content' => $revJson]]]], 200);
        });
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertStatus(502)->assertJsonPath('code', 'provider_error');

        $fresh = $build->fresh();
        $this->assertSame(1, $fresh->version);
        $this->assertCount(1, $fresh->versions);

        // Retry with a healthy provider produces v2.
        $failUpstream = false;
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertOk()->assertJsonPath('master.version', 2);
    }

    public function test_master_state_recoverable_from_server(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $build = $this->startChain($user, $conv->id)['build'];
        $this->fakeRevisionModel($this->revisedOutline());
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان را عوض کن']
        )->assertOk();
        $this->approve($user, $build->fresh(), 2)->assertOk();

        // Simulated page reload: full master state comes from the server.
        $res = $this->actingAs($user)->getJson(route('ai.study-builds.show', $build))->assertOk();
        $res->assertJsonPath('master.version', 2)
            ->assertJsonPath('master.approved_version', 2);
        $versions = $res->json('master.versions');
        $this->assertCount(2, $versions);
        $this->assertSame('superseded', $versions[0]['status']);
        $this->assertSame('approved', $versions[1]['status']);
        $this->assertSame(16, count($res->json('master.outline.chapters')));
        $this->assertTrue($res->json('master.validation.ok'));
    }

    public function test_standalone_plan_bypasses_master_gate(): void
    {
        $user = User::factory()->create();
        $svc = new \App\Services\AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Solo',
            'project' => ['name' => 'تکی', 'tasks' => [['title' => 'کار ۱']]],
        ], $user);
        $this->assertTrue($check['ok']);
        $plan = AiPlan::create([
            'user_id' => $user->id,
            'title' => $check['resolved']['title'],
            'structure' => $check['resolved']['structure'],
            'phases' => $svc->buildPlanPhases($check['resolved']['structure']),
            'status' => AiPlan::STATUS_PROPOSED,
            'current_phase' => 0,
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);

        // No chain involved: the classic unconfirmed-structure guard applies.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'not_confirmed');
    }
}
