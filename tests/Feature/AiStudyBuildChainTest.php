<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\AiStudyBuild;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * Phase 3: the multi-chunk chain (Model B — one confirmation per chunk).
 * Drives a real book build through HTTP: chunk 1 builds the book project,
 * chunk 2 attaches to the SAME project (never a duplicate), cancelling a
 * head plan stops the chain, and a lost advance response is recoverable.
 */
class AiStudyBuildChainTest extends TestCase
{
    use RefreshDatabase;

    private function startChain(User $user): array
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $result = StudyBuildOrchestrator::startFromOutline($user, null, $outline);

        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'start failed');

        return $result;
    }

    private function approveMaster(User $user, AiPlan $plan): void
    {
        // P1: packages stay locked until the master version is approved.
        $build = AiStudyBuild::where('user_id', $user->id)->where(function ($q) use ($plan) {
            $q->where('last_plan_id', $plan->id)->orWhereJsonContains('plan_ids', (int) $plan->id);
        })->firstOrFail();
        $this->actingAs($user)->postJson(route('ai.study-builds.approve', $build), ['version' => 1])->assertOk();
    }

    private function confirmWholePlan(User $user, AiPlan $plan): \Illuminate\Testing\TestResponse
    {
        $this->approveMaster($user, $plan);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();

        return $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $plan),
            ['run_all' => true]
        );
    }

    public function test_start_proposes_chunk_one_of_six(): void
    {
        $user = User::factory()->create();
        $result = $this->startChain($user);

        /** @var AiStudyBuild $build */
        $build = $result['build'];
        $this->assertSame(6, $build->total_chunks);
        $this->assertSame(0, $build->done_chunks);
        $this->assertSame(AiStudyBuild::STATUS_BUILDING, $build->status);
        $this->assertSame('روانشناسی رشد', $build->title);

        /** @var AiPlan $plan */
        $plan = $result['plan'];
        $this->assertSame(AiPlan::STATUS_PROPOSED, $plan->status);
        $roots = $plan->structure['projects'];
        $this->assertCount(1, $roots);
        $this->assertSame('روانشناسی رشد', $roots[0]['name']);
        $this->assertCount(3, $roots[0]['subprojects']);
        $this->assertSame([$plan->id], $build->fresh()->plan_ids);
        $this->assertSame($plan->id, $build->fresh()->last_plan_id);
    }

    public function test_chunk_one_builds_book_and_proposes_chunk_two_attached(): void
    {
        $user = User::factory()->create();
        $result = $this->startChain($user);
        $plan = $result['plan'];

        $response = $this->confirmWholePlan($user, $plan);
        $response->assertOk()->assertJsonPath('plan.status', 'done');

        // Book project created exactly once, with 3 chapter sub-projects.
        $book = Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail();
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
        $this->assertSame(3, Project::where('user_id', $user->id)->where('parent_id', $book->id)->count());

        // Lessons are tasks with pages in description + weight, titles clean.
        // (Server normalizes digits to Latin on write — same as all plans.)
        $lesson = Task::where('user_id', $user->id)->where('title', 'درسنامه ۱: کلیات روانشناسی رشد')->firstOrFail();
        $this->assertSame('📄 10 صفحه', $lesson->description);
        $this->assertEquals(10, $lesson->weight);
        $this->assertSame(5, Task::where('parent_id', $lesson->id)->count());

        // Chain advanced: next chunk proposed, progress tracked.
        $response->assertJsonPath('study_build.done_chunks', 1);
        $response->assertJsonPath('study_build.total_chunks', 6);
        $nextId = $response->json('next_plan.id');
        $this->assertNotNull($nextId);

        $next = AiPlan::findOrFail($nextId);
        $this->assertSame(AiPlan::STATUS_PROPOSED, $next->status);
        $nextRoot = $next->structure['projects'][0];
        $this->assertSame($book->id, $nextRoot['project_id']);
        $this->assertTrue((bool) $nextRoot['attached']);

        // Lost-response safety: re-running the finished plan re-surfaces chunk 2.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertOk()
            ->assertJsonPath('deduped', true)
            ->assertJsonPath('next_plan.id', $nextId);
    }

    public function test_chunk_two_attaches_without_duplicating_book(): void
    {
        $user = User::factory()->create();
        $result = $this->startChain($user);

        $first = $this->confirmWholePlan($user, $result['plan'])->assertOk();
        $second = AiPlan::findOrFail($first->json('next_plan.id'));

        $this->confirmWholePlan($user, $second)
            ->assertOk()
            ->assertJsonPath('plan.status', 'done')
            ->assertJsonPath('study_build.done_chunks', 2);

        // Still exactly one book project; now 6 chapters beneath it.
        $book = Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail();
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
        $this->assertSame(6, Project::where('user_id', $user->id)->where('parent_id', $book->id)->count());

        // Build recorded the book project; chunk 3 is already proposed
        // (advance runs on every finished head), chain still building.
        $build = AiStudyBuild::firstOrFail();
        $this->assertSame($book->id, (int) $build->book_project_id);
        $this->assertCount(3, $build->plan_ids);
        $this->assertSame(2, $build->done_chunks);
        $this->assertSame(AiStudyBuild::STATUS_BUILDING, $build->status);
        $this->assertSame(AiPlan::STATUS_PROPOSED, AiPlan::findOrFail($build->last_plan_id)->status);
    }

    public function test_cancelling_head_plan_stops_chain(): void
    {
        $user = User::factory()->create();
        $result = $this->startChain($user);

        $first = $this->confirmWholePlan($user, $result['plan'])->assertOk();
        $second = AiPlan::findOrFail($first->json('next_plan.id'));

        $this->actingAs($user)->postJson(route('ai.plans.cancel', $second))
            ->assertOk()
            ->assertJsonPath('study_build.status', AiStudyBuild::STATUS_CANCELLED);

        $this->assertSame(AiStudyBuild::STATUS_CANCELLED, AiStudyBuild::firstOrFail()->status);
        // Built chunks stay (Model B rule, same as single plans).
        $this->assertSame(1, Project::where('user_id', $user->id)->where('parent_id', null)->where('name', 'روانشناسی رشد')->count());
    }

    public function test_empty_outline_is_rejected_without_creating_anything(): void
    {
        $user = User::factory()->create();

        $result = StudyBuildOrchestrator::startFromOutline($user, null, ['book_title' => 'x', 'chapters' => []]);

        $this->assertFalse($result['ok']);
        $this->assertSame(0, AiStudyBuild::count());
        $this->assertSame(0, AiPlan::count());
    }
}
