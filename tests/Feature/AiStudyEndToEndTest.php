<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiPlan;
use App\Models\AiSetting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use App\Services\Study\StudyPlanChunker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * Phase 5: the full multi-chapter journey in ONE flow — request summary,
 * master review + revision, master approval, package-list approval, first
 * package preview, explicit confirm + execution, refresh recovery, failure
 * + retry, and continuation — with title/number/range/page consistency
 * checked at every step.
 */
class AiStudyEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function withProvider(User $user): void
    {
        AiSetting::create([
            'user_id' => $user->id,
            'default_provider' => 'openai',
            'openai_key' => 'sk-test-key',
            'openai_model' => 'gpt-4o-mini',
        ]);
    }

    private function excerpt(): array
    {
        return StudyOutlineParser::parse(StudyOutlineFixture::cognitiveRegressionExcerpt());
    }

    private function startChain(User $user, AiConversation $conv): array
    {
        $result = StudyBuildOrchestrator::startFromOutline($user, $conv->id, $this->excerpt());
        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'start failed');

        return $result;
    }

    private function approveMaster(User $user, $build, int $version = 1)
    {
        return $this->actingAs($user)->postJson(
            route('ai.study-builds.approve', $build), ['version' => $version]
        );
    }

    private function approvePackages(User $user, $build, int $version = 1)
    {
        return $this->actingAs($user)->postJson(
            route('ai.study-builds.approve-packages', $build), ['version' => $version]
        );
    }

    private function recover(User $user, AiConversation $conv): array
    {
        return $this->actingAs($user)->getJson(
            route('ai.conversations.study-builds', $conv)
        )->assertOk()->json('builds');
    }

    public function test_full_journey_from_request_to_done_chain(): void
    {
        app()->setLocale('fa');
        $user = User::factory()->create();
        $this->withProvider($user);
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);

        // ── 1) Intake: inline title, chapters, lessons, pages ──
        $started = $this->startChain($user, $conv);
        $build = $started['build'];
        $head = $started['plan'];

        $this->assertSame('کتاب روانشناسی شناختی', $build->title);
        $outline = $build->outline_json;
        $this->assertCount(4, $outline['chapters']);
        $this->assertSame([1, 2, 3, 4], array_column($outline['chapters'], 'number'));
        $lessons = array_sum(array_map(fn ($ch) => count($ch['lessons']), $outline['chapters']));
        $this->assertSame(14, $lessons);
        // Every chapter's pages equal the sum of its lessons' pages.
        foreach ($outline['chapters'] as $ch) {
            $this->assertSame(
                (int) $ch['pages'],
                (int) array_sum(array_column($ch['lessons'], 'pages')),
                'chapter pages must match lesson pages: '.$ch['title']
            );
        }
        // Chunk ranges use real numbers and cover chapters 1-4 exactly once.
        $chunks = $build->chunks_json;
        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('فصل 1 تا 3', $chunks[0]['title']);
        $this->assertStringContainsString('فصل 4', $chunks[1]['title']);
        $this->assertSame(160, (int) $chunks[0]['totals']['pages']);
        $this->assertSame(60, (int) $chunks[1]['totals']['pages']);

        // ── 2) Master review: nothing executable, out-of-order refused ──
        $recovered = $this->recover($user, $conv)[0];
        $this->assertSame('master_review', $recovered['master']['workflow_stage']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $head))
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');
        $this->approvePackages($user, $build)
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');

        // ── 2b) Revision creates v2, preserves v1, keeps scope per version ──
        // One fake for the whole journey: Http::fake() stubs merge (first
        // match wins), so sequential phases switch behaviour with a flag.
        $revOutline = $this->excerpt();
        $revOutline['chapters'][0]['title'] = 'فصل ۱: درآمدی بر روان‌شناسی شناختی';
        $pkgArgs = StudyBuildOrchestrator::buildPlanArgs(StudyPlanChunker::chunk($this->excerpt())[1], null);
        $fakeMode = 'master';
        Http::fake(function () use (&$fakeMode, $revOutline, $pkgArgs) {
            $content = $fakeMode === 'master'
                ? json_encode($revOutline, JSON_UNESCAPED_UNICODE)
                : json_encode($pkgArgs, JSON_UNESCAPED_UNICODE);

            return Http::response(['choices' => [['message' => ['content' => $content]]]], 200);
        });
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => 'عنوان فصل اول را کامل کن']
        )->assertOk()->assertJsonPath('master.version', 2);

        $fresh = $build->fresh();
        $this->assertCount(2, $fresh->versions);
        $this->assertSame($outline['chapters'][0]['title'], $fresh->versions[0]['outline']['chapters'][0]['title']);
        $this->assertSame('فصل ۱: درآمدی بر روان‌شناسی شناختی', $fresh->versions[1]['outline']['chapters'][0]['title']);
        $recovered = $this->recover($user, $conv)[0];
        $this->assertSame('master_review', $recovered['master']['workflow_stage']);
        $pkgs = collect($recovered['packages']);
        $oldHead = $pkgs->first(fn ($p) => (int) $p['master_version'] === 1 && $p['plan_id']);
        $newHead = $pkgs->first(fn ($p) => (int) $p['master_version'] === 2 && $p['plan_id']);
        $this->assertSame(['view'], $oldHead['actions']);
        $this->assertStringContainsString('مقدمه‌ای بر روان‌شناسی شناختی', $oldHead['scope'][0]);
        $this->assertStringContainsString('درآمدی بر روان‌شناسی شناختی', $newHead['scope'][0]);

        // Old version stays rejected; latest needs its own approvals.
        $this->approveMaster($user, $fresh, 1)->assertStatus(409);
        $this->approveMaster($user, $fresh, 2)->assertOk();
        $this->approvePackages($user, $fresh, 1)->assertStatus(409);
        $this->approvePackages($user, $fresh, 2)->assertOk()
            ->assertJsonPath('master.packages_approved', true);

        // ── 4-5) Packages approved → head preview carries open gates ──
        $recovered = $this->recover($user, $conv)[0];
        $this->assertSame('preview_review', $recovered['master']['workflow_stage']);
        $headPkg = collect($recovered['packages'])->firstWhere('is_head', true);
        $this->assertTrue($headPkg['plan']['chain']['master_approved']);
        $this->assertTrue($headPkg['plan']['chain']['packages_approved']);
        $this->assertContains('confirm', $headPkg['actions']);

        // ── 6) Explicit preview confirm creates nothing; execution builds ──
        $newHead = AiPlan::where('user_id', $user->id)->where('status', AiPlan::STATUS_PROPOSED)->firstOrFail();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $newHead))->assertOk();
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $done = $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $newHead), ['run_all' => true]
        )->assertOk()->assertJsonPath('plan.status', 'done');

        $book = Project::where('user_id', $user->id)->where('name', 'کتاب روانشناسی شناختی')->firstOrFail();
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'کتاب روانشناسی شناختی')->count());
        $this->assertSame(3, Project::where('user_id', $user->id)->where('parent_id', $book->id)->count());
        $this->assertSame(10, Task::where('user_id', $user->id)->whereNotNull('project_id')->whereNull('parent_id')->count());
        $this->assertSame(160.0, (float) Task::where('user_id', $user->id)->whereNotNull('project_id')->whereNull('parent_id')->sum('weight'));
        $nextId = $done->json('next_plan.id');
        $this->assertNotNull($nextId);

        // ── 7) Recovery after progress: stage + head + history ──
        $recovered = $this->recover($user, $conv)[0];
        $this->assertSame('preview_review', $recovered['master']['workflow_stage']);
        $this->assertSame(1, $recovered['study_build']['done_chunks']);

        // ── 8) Failure, honest retry, continuation to done ──
        $pkg2 = AiPlan::findOrFail($nextId);
        Project::where('user_id', $user->id)->delete();
        $tasksBefore = Task::where('user_id', $user->id)->count();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $pkg2))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'phase_failed');
        $failed = $this->recover($user, $conv)[0];
        $this->assertSame('failed', $failed['master']['workflow_stage']);
        $failedHead = collect($failed['packages'])->firstWhere('is_head', true);
        $this->assertSame(AiPlan::STATUS_FAILED, $failedHead['plan']['status']);
        $this->assertNotEmpty($failedHead['plan']['last_error']);
        $this->assertContains('execute', $failedHead['actions']);
        // Retry changes nothing and duplicates nothing.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'phase_failed');
        $this->assertSame($tasksBefore, Task::where('user_id', $user->id)->count());

        // Revise the failed package within scope → execute → chain done.
        // (Same single fake, switched to package mode.)
        $fakeMode = 'package';
        $revisedId = $this->actingAs($user)->postJson(
            route('ai.plans.revise-package', $pkg2), ['feedback' => 'کتاب حذف شده؛ از نو بساز']
        )->assertOk()->json('plan.id');
        $revised = AiPlan::findOrFail($revisedId);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $revised))->assertOk();
        $fin = $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $revised), ['run_all' => true]
        )->assertOk()->assertJsonPath('plan.status', 'done');
        $this->assertSame('done', $fin->json('study_build.status'));
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'کتاب روانشناسی شناختی')->count());

        $closed = $this->recover($user, $conv)[0];
        $this->assertSame('done', $closed['master']['workflow_stage']);
        foreach ($closed['packages'] as $pkg) {
            $this->assertSame(['view'], $pkg['actions']);
        }

        // Re-running the finished chain resurfaces nothing new.
        $again = $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $revised), ['run_all' => true]
        )->assertOk()->assertJsonPath('deduped', true);
        $this->assertNull($again->json('next_plan.id'));
    }

    public function test_out_of_order_matrix_rejected(): void
    {
        $user = User::factory()->create();
        $started = StudyBuildOrchestrator::startFromOutline($user, null, $this->excerpt());
        $this->assertTrue($started['ok'] ?? false);
        $build = $started['build'];
        $plan = $started['plan'];

        // Execute before anything: refused, nothing created.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');

        // Revise with empty feedback: validation refuses before any work.
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $build), ['feedback' => '']
        )->assertStatus(422);
        $this->assertSame(1, $build->fresh()->version);

        // Unknown master version: 404-style rejection, state untouched.
        $this->approveMaster($user, $build, 9)->assertStatus(409);
        $this->assertNull($build->fresh()->approved_version);

        // Double master approval: second is a harmless dedupe.
        $this->approveMaster($user, $build, 1)->assertOk()->assertJsonPath('deduped', false);
        $this->approveMaster($user, $build->fresh(), 1)->assertOk()->assertJsonPath('deduped', true);
    }

    public function test_stage_errors_speak_persian(): void
    {
        app()->setLocale('fa');
        $user = User::factory()->create();
        $started = StudyBuildOrchestrator::startFromOutline($user, null, $this->excerpt());
        $this->assertTrue($started['ok'] ?? false);
        $build = $started['build'];
        $plan = $started['plan'];

        $res = $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertStatus(422)->assertJsonPath('code', 'master_not_approved');
        $this->assertStringContainsString('قفل', (string) $res->json('error'));

        $this->approveMaster($user, $build, 1)->assertOk();
        $res = $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertStatus(422)->assertJsonPath('code', 'packages_not_approved');
        $this->assertStringContainsString('قفل', (string) $res->json('error'));

        $this->approvePackages($user, $build, 1)->assertOk();
        // Unknown version: Persian, stateless.
        $res = $this->approveMaster($user, $build->fresh(), 9)->assertStatus(409);
        $this->assertStringContainsString('نامشخص', (string) $res->json('error'));

        // Cancelling the head stops the chain; later ops say so in Persian.
        $this->actingAs($user)->postJson(route('ai.plans.cancel', $plan))->assertOk();
        $res = $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan->fresh()))
            ->assertStatus(422);
        $this->assertStringContainsString('منقضی', (string) $res->json('error'));
    }

    public function test_stale_plan_from_retired_version_cannot_execute(): void
    {
        app()->setLocale('fa');
        $user = User::factory()->create();
        $this->withProvider($user);
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'c']);
        $started = $this->startChain($user, $conv);
        $revOutline = $this->excerpt();
        $revOutline['book_title'] = 'کتاب روانشناسی شناختی (ویرایش‌شده)';
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode($revOutline, JSON_UNESCAPED_UNICODE)]]],
        ], 200)]);
        $this->actingAs($user)->postJson(
            route('ai.study-builds.revise', $started['build']), ['feedback' => 'عوض کن']
        )->assertOk();
        $this->approveMaster($user, $started['build']->fresh(), 2)->assertOk();
        $this->approvePackages($user, $started['build']->fresh(), 2)->assertOk();

        // The retired v1 head can neither confirm nor execute, in Persian.
        $old = $started['plan']->fresh();
        $this->assertSame(AiPlan::STATUS_OBSOLETE, $old->status);
        $res = $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $old))
            ->assertStatus(422)->assertJsonPath('code', 'superseded');
        $this->assertStringContainsString('جایگزین', (string) $res->json('error'));
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
    }
}
