<?php

namespace Tests\Feature;

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
 * P2 packages: independent, reviewable, revisable units of an approved
 * Master Plan. No provider, no real HTTP — revision calls are faked.
 */
class AiStudyPackageTest extends TestCase
{
    use RefreshDatabase;

    private function startChain(User $user): array
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $result = StudyBuildOrchestrator::startFromOutline($user, null, $outline);
        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'start failed');

        return $result;
    }

    private function approve(User $user, AiStudyBuild $build, int $version = 1)
    {
        return $this->actingAs($user)->postJson(
            route('ai.study-builds.approve', $build), ['version' => $version]
        )->assertOk();
    }

    /** Craft (but do not execute) chunk N of a build — simulates a later head. */
    private function craftPackage(User $user, AiStudyBuild $build, int $chunkIdx): AiPlan
    {
        $chunks = $build->fresh()->chunks_json;
        $created = StudyBuildOrchestrator::createChunkPlan($user, null, $build->fresh(), $chunks[$chunkIdx]);
        $this->assertTrue($created['ok'] ?? false, $created['error'] ?? 'craft failed');

        return $created['plan'];
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

    private function fakePackageModel(array $args): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($args, JSON_UNESCAPED_UNICODE)]]],
        ], 200)]);
    }

    public function test_packages_listed_with_versions_and_locked_actions(): void
    {
        $user = User::factory()->create();
        $build = $this->startChain($user)['build'];

        $res = $this->actingAs($user)->getJson(route('ai.study-builds.show', $build))->assertOk();
        $packages = $res->json('packages');
        $this->assertCount(6, $packages);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($packages, 'package_index'));
        foreach ($packages as $pkg) {
            $this->assertSame(1, $pkg['master_version']);
            $this->assertTrue($pkg['is_current_version']);
            $this->assertNotEmpty($pkg['scope']);
            $this->assertSame(['view'], $pkg['actions'], 'unapproved master locks every package');
        }
        // Head package is a real proposal; the rest are planned slots
        // (valid view from stored chunks — no rows created for them).
        $this->assertSame('proposed', $packages[0]['status']);
        $this->assertNotNull($packages[0]['plan_id']);
        foreach (array_slice($packages, 1) as $slot) {
            $this->assertSame('planned', $slot['status']);
            $this->assertNull($slot['plan_id']);
        }
        $this->assertSame(1, AiPlan::where('user_id', $user->id)->count());
        $this->assertSame([1], $packages[1]['depends_on']);
        $this->assertSame([], $packages[0]['depends_on']);
    }

    public function test_every_package_links_to_master_version(): void
    {
        $user = User::factory()->create();
        $build = $this->startChain($user)['build'];

        foreach (AiPlan::where('user_id', $user->id)->get() as $plan) {
            $this->assertSame($build->id, (int) $plan->master_build_id);
            $this->assertSame(1, (int) $plan->master_version);
            $this->assertSame(1, (int) $plan->package_index);
        }
    }

    public function test_independent_package_confirm_and_execute(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());

        $show = $this->actingAs($user)->getJson(route('ai.study-builds.show', $started['build']))->assertOk();
        $byIndex = collect($show->json('packages'))->keyBy('package_index');
        $this->assertSame('done', $byIndex[1]['status']);
        $this->assertSame('proposed', $byIndex[2]['status']);
    }

    public function test_dependent_package_blocked_before_prerequisite(): void
    {
        $user = User::factory()->create();
        $build = $this->startChain($user)['build'];
        $this->approve($user, $build);

        $pkg2 = $this->craftPackage($user, $build, 1);

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $pkg2))
            ->assertStatus(422)->assertJsonPath('code', 'dependency_blocked');
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'dependency_blocked');

        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_revise_package_preserves_independent_ones(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $head = $started['plan'];

        $chunks = $started['build']->fresh()->chunks_json;
        $args = StudyBuildOrchestrator::buildPlanArgs($chunks[0], null);
        $args['subprojects'][0]['tasks'][0]['title'] .= ' (ویرایش‌شده)';
        $this->fakePackageModel($args);

        $res = $this->actingAs($user)->postJson(
            route('ai.plans.revise-package', $head), ['feedback' => 'درس اول را شفاف‌تر کن']
        )->assertOk();

        // Old proposal retired as obsolete + linked; exactly one new plan.
        $this->assertSame(AiPlan::STATUS_OBSOLETE, $head->fresh()->status);
        $newId = $res->json('plan.id');
        $this->assertSame($newId, (int) $head->fresh()->superseded_by_plan_id);
        $this->assertSame(2, AiPlan::where('user_id', $user->id)->count());
        $new = AiPlan::findOrFail($newId);
        $this->assertSame(AiPlan::STATUS_PROPOSED, $new->status);
        $this->assertSame(1, (int) $new->package_index);
        $this->assertSame(1, (int) $new->master_version);
        $this->assertSame([], $res->json('affected'));
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_dependent_packages_flagged_as_affected(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $pkg2 = $this->craftPackage($user, $started['build'], 1);

        $chunks = $started['build']->fresh()->chunks_json;
        $args = StudyBuildOrchestrator::buildPlanArgs($chunks[0], null);
        $args['subprojects'][0]['tasks'][0]['title'] .= ' (ویرایش‌شده)';
        $this->fakePackageModel($args);

        $res = $this->actingAs($user)->postJson(
            route('ai.plans.revise-package', $started['plan']), ['feedback' => 'درس اول را شفاف‌تر کن']
        )->assertOk()->assertJsonPath('affected', [$pkg2->id]);

        // The independent later package is untouched.
        $this->assertSame(AiPlan::STATUS_PROPOSED, $pkg2->fresh()->status);
    }

    public function test_revised_package_reapproved_and_executed(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);

        $chunks = $started['build']->fresh()->chunks_json;
        $args = StudyBuildOrchestrator::buildPlanArgs($chunks[0], null);
        $args['subprojects'][0]['tasks'][0]['title'] .= ' (ویرایش‌شده)';
        $this->fakePackageModel($args);
        $newId = $this->actingAs($user)->postJson(
            route('ai.plans.revise-package', $started['plan']), ['feedback' => 'x']
        )->assertOk()->json('plan.id');

        $new = AiPlan::findOrFail($newId);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $new))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $new), ['run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
    }

    public function test_obsolete_package_cannot_execute(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);

        $chunks = $started['build']->fresh()->chunks_json;
        $this->fakePackageModel(StudyBuildOrchestrator::buildPlanArgs($chunks[0], null));
        $this->actingAs($user)->postJson(
            route('ai.plans.revise-package', $started['plan']), ['feedback' => 'x']
        )->assertOk();

        $old = $started['plan']->fresh();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $old))
            ->assertStatus(422)->assertJsonPath('code', 'superseded');
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $old), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'superseded');

        $this->assertSame(0, Project::where('user_id', $user->id)->count());
    }

    public function test_packages_recoverable_from_server_after_reload(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();

        // Simulated reload: full package state comes from the server.
        $first = $this->actingAs($user)->getJson(route('ai.study-builds.show', $started['build']))->assertOk();
        $second = $this->actingAs($user)->getJson(route('ai.study-builds.show', $started['build']))->assertOk();
        $this->assertSame($first->json('packages'), $second->json('packages'));
        $byIndex = collect($second->json('packages'))->keyBy('package_index');
        $this->assertSame('done', $byIndex[1]['status']);
        $this->assertSame('proposed', $byIndex[2]['status']);
        $this->assertContains('execute', $byIndex[2]['actions']);
    }

    public function test_repeat_execution_never_duplicates(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $tasks = Task::where('user_id', $user->id)->count();
        $projects = Project::where('user_id', $user->id)->count();

        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])
            ->assertOk()->assertJsonPath('deduped', true);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))
            ->assertOk()->assertJsonPath('deduped', true);

        $this->assertSame($tasks, Task::where('user_id', $user->id)->count());
        $this->assertSame($projects, Project::where('user_id', $user->id)->count());
    }

    public function test_package_endpoints_enforce_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $started = $this->startChain($owner);

        $this->actingAs($other)->getJson(route('ai.study-builds.show', $started['build']))->assertForbidden();
        $this->actingAs($other)->postJson(route('ai.plans.revise-package', $started['plan']), ['feedback' => 'x'])->assertForbidden();

        $this->assertSame(AiPlan::STATUS_PROPOSED, $started['plan']->fresh()->status);
    }

    public function test_failed_package_recorded_and_recovered_safely(): void
    {
        $user = User::factory()->create();
        $this->withProvider($user);
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);

        // Execute chunk 1 fully; chunk 2 attaches to the real book.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $done = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $pkg2Id = $done->json('next_plan.id');
        $this->assertNotNull($pkg2Id);
        $pkg2 = AiPlan::findOrFail($pkg2Id);
        $bookId = Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail()->id;

        // User deletes the shared book mid-chain: the attach fails honestly.
        Project::where('id', $bookId)->delete();
        $tasksBefore = Task::where('user_id', $user->id)->count();

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $pkg2))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'phase_failed');

        $pkg2 = $pkg2->fresh();
        $this->assertSame(AiPlan::STATUS_FAILED, $pkg2->status);
        $this->assertNotEmpty($pkg2->last_error);
        $this->assertSame($tasksBefore, Task::where('user_id', $user->id)->count());
        // The chain did not advance past the failed package.
        $build = AiStudyBuild::firstOrFail();
        $this->assertSame(AiStudyBuild::STATUS_BUILDING, $build->status);
        $this->assertSame($pkg2->id, (int) $build->last_plan_id);
        $this->assertCount(2, $build->plan_ids);

        // Repeating the doomed run is idempotent — still failed, no dupes.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'phase_failed');
        $this->assertSame($tasksBefore, Task::where('user_id', $user->id)->count());

        // Safe recovery: revise the failed package (book gone → rebuild
        // under the same name is allowed). Old failure stays on record.
        $chunks = $started['build']->fresh()->chunks_json;
        $args = StudyBuildOrchestrator::buildPlanArgs($chunks[1], null);
        $args['subprojects'][0]['tasks'][0]['title'] .= ' (بازیابی‌شده)';
        $this->fakePackageModel($args);
        $newId = $this->actingAs($user)->postJson(
            route('ai.plans.revise-package', $pkg2), ['feedback' => 'کتاب حذف شده؛ از نو بساز']
        )->assertOk()->json('plan.id');

        $this->assertSame(AiPlan::STATUS_OBSOLETE, $pkg2->fresh()->status);
        $new = AiPlan::findOrFail($newId);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $new))->assertOk();
        $fin = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $new), ['run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        // Fresh book rebuilt once; chain advanced to chunk 3.
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
        $this->assertNotNull($fin->json('next_plan.id'));
        $this->assertSame(2, $fin->json('study_build.done_chunks'));
    }
}
