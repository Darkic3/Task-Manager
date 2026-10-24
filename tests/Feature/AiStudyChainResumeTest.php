<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiPlan;
use App\Models\AiStudyBuild;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiToolService;
use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * P0: chain continuation without the model, guarded execution, honest
 * reporting. No provider, no real HTTP: the stored chain (chunks_json +
 * book_project_id) is the only source for the next package.
 */
class AiStudyChainResumeTest extends TestCase
{
    use RefreshDatabase;

    private function startChain(User $user, ?int $conversationId = null): array
    {
        $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
        $result = StudyBuildOrchestrator::startFromOutline($user, $conversationId, $outline);

        $this->assertTrue($result['ok'] ?? false, $result['error'] ?? 'start failed');

        return $result;
    }

    private function confirmWholePlan(User $user, AiPlan $plan): \Illuminate\Testing\TestResponse
    {
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();

        return $this->actingAs($user)->postJson(
            route('ai.plans.confirm-phase', $plan),
            ['run_all' => true]
        );
    }

    private function makePlan(User $user, array $args): AiPlan
    {
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', $args, $user);
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

    public function test_done_head_auto_proposes_next_with_chain_report(): void
    {
        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];

        $res = $this->confirmWholePlan($user, $plan)
            ->assertOk()
            ->assertJsonPath('plan.status', 'done');

        $nextId = $res->json('next_plan.id');
        $this->assertNotNull($nextId);

        // Report comes from server records: one done package, one proposed.
        $report = $res->json('chain_report');
        $this->assertNotNull($report);
        $this->assertSame(6, $report['total_chunks']);
        $this->assertSame(1, $report['done_chunks']);
        $this->assertCount(2, $report['items']);
        $this->assertSame('done', $report['items'][0]['status']);
        $this->assertSame('proposed', $report['items'][1]['status']);
        $this->assertSame($nextId, $report['items'][1]['id']);
    }

    public function test_repeated_continue_returns_same_head_without_duplicate(): void
    {
        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];
        $first = $this->confirmWholePlan($user, $plan)->assertOk();
        $nextId = $first->json('next_plan.id');
        $this->assertNotNull($nextId);
        $this->assertSame(2, AiPlan::where('user_id', $user->id)->count());

        // Two repeated continuations on the DONE head: same package, no copy.
        foreach (range(1, 2) as $i) {
            $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
                ->assertOk()
                ->assertJsonPath('deduped', true)
                ->assertJsonPath('next_plan.id', $nextId);
        }

        $this->assertSame(2, AiPlan::where('user_id', $user->id)->count());
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
    }

    public function test_manual_continue_uses_stored_chain_without_provider(): void
    {
        // Any outgoing provider HTTP fails the test outright.
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];
        $auto = $this->confirmWholePlan($user, $plan)->assertOk();
        $nextId = $auto->json('next_plan.id');
        $this->assertNotNull($nextId);

        // Manual 📦 tap = same endpoint, no original text, no model involved.
        // Simulate a lost auto-response by deleting nothing: the DONE branch
        // must resurface the already-proposed head from stored records.
        $manual = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), [])
            ->assertOk()
            ->assertJsonPath('deduped', true)
            ->assertJsonPath('next_plan.id', $nextId);

        // Next package attaches to the stored book id — never a guessed name.
        $next = AiPlan::findOrFail($nextId);
        $this->assertSame(
            Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail()->id,
            (int) $next->structure['projects'][0]['project_id']
        );
        $this->assertSame(2, AiPlan::where('user_id', $user->id)->count());
        $this->assertNotNull($manual->json('chain_report'));
    }

    public function test_done_plan_without_chain_returns_deduped_without_changes(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user, [
            'title' => 'Solo',
            'project' => ['name' => 'تکی', 'tasks' => [['title' => 'کار ۱']]],
        ]);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        $projectsBefore = Project::where('user_id', $user->id)->count();
        $tasksBefore = Task::where('user_id', $user->id)->count();

        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), [])
            ->assertOk()
            ->assertJsonPath('deduped', true)
            ->assertJsonMissingPath('next_plan');

        $this->assertSame($projectsBefore, Project::where('user_id', $user->id)->count());
        $this->assertSame($tasksBefore, Task::where('user_id', $user->id)->count());
    }

    public function test_proposed_plan_cannot_execute_without_confirm_and_stays_proposed(): void
    {
        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];
        $expires = $plan->expires_at->toIso8601String();

        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertStatus(422)
            ->assertJsonPath('code', 'not_confirmed');

        // Rejected safely: still proposed, NOT expired, nothing built.
        $fresh = $plan->fresh();
        $this->assertSame(AiPlan::STATUS_PROPOSED, $fresh->status);
        $this->assertSame($expires, $fresh->expires_at->toIso8601String());
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_executing_plan_runs_next_phase(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan($user, [
            'title' => 'TwoPhase',
            'project' => ['name' => 'ریشه', 'tasks' => []],
            'subprojects' => [['name' => 'شاخه', 'tasks' => [['title' => 'کار شعبه']]]],
        ]);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))->assertOk();

        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => 0])
            ->assertOk();
        $root = Project::where('user_id', $user->id)->where('name', 'ریشه')->firstOrFail();

        // Later phases of an executing plan stay allowed (P0 must not block them).
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => $plan->fresh()->current_phase])
            ->assertOk();
        $sub = Project::where('user_id', $user->id)->where('name', 'شاخه')->firstOrFail();
        $this->assertSame($root->id, (int) $sub->parent_id);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['phase' => $plan->fresh()->current_phase])
            ->assertOk();
        $this->assertSame(1, Task::where('user_id', $user->id)->where('title', 'کار شعبه')->count());
    }

    public function test_done_plan_repeat_confirm_returns_deduped(): void
    {
        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];
        $this->confirmWholePlan($user, $plan)->assertOk();
        $plansBefore = AiPlan::where('user_id', $user->id)->count();

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertOk()->assertJsonPath('deduped', true);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertOk()->assertJsonPath('deduped', true);

        $this->assertSame($plansBefore, AiPlan::where('user_id', $user->id)->count());
    }

    public function test_cancelled_chain_forbids_continue(): void
    {
        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];
        $first = $this->confirmWholePlan($user, $plan)->assertOk();
        $head = AiPlan::findOrFail($first->json('next_plan.id'));

        $this->actingAs($user)->postJson(route('ai.plans.cancel', $head))->assertOk();
        $this->assertSame(AiStudyBuild::STATUS_CANCELLED, AiStudyBuild::firstOrFail()->status);

        // Recovery on the DONE head: cancelled build yields nothing new.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $plan), [])
            ->assertOk()
            ->assertJsonPath('deduped', true)
            ->assertJsonMissingPath('next_plan');

        // The cancelled head itself cannot run.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $head), ['run_all' => true])
            ->assertStatus(422);
        $this->assertSame(2, AiPlan::where('user_id', $user->id)->count());
    }

    public function test_empty_plan_error_points_at_pending_head(): void
    {
        $user = User::factory()->create();
        $conv = AiConversation::create(['user_id' => $user->id, 'label' => 'chain']);
        $head = $this->startChain($user, $conv->id)['plan'];

        $controller = new \App\Http\Controllers\AiChatController(app(\App\Services\AiProviderService::class));
        $ref = new \ReflectionMethod($controller, 'createPlanFromToolCall');
        $ref->setAccessible(true);

        $out = $ref->invoke($controller, $user, $conv->id, ['title' => 'خالی']);

        $this->assertArrayHasKey('error', $out);
        $this->assertStringContainsString('empty', strtolower($out['error']));
        // Recovery pointer at the already-proposed head — no new plan.
        $this->assertSame($head->id, $out['chain_continue']['plan_id'] ?? null);
        $this->assertSame(1, AiPlan::where('user_id', $user->id)->count());
    }

    public function test_rollback_keeps_unrelated_projects(): void
    {
        $user = User::factory()->create();
        $keeper = Project::create(['user_id' => $user->id, 'name' => 'پروژه شخصی قدیمی', 'status' => 'in_progress', 'type' => 'project', 'sort_order' => 0]);
        $plan = $this->startChain($user)['plan'];
        $this->confirmWholePlan($user, $plan)->assertOk();

        $bookId = Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail()->id;

        $this->actingAs($user)->postJson(route('ai.plans.rollback', $plan))->assertOk();

        $this->assertNull(Project::find($bookId));
        $this->assertNotNull(Project::find($keeper->id));
    }

    public function test_foreign_chain_head_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $plan = $this->startChain($owner)['plan'];

        $this->actingAs($other)->postJson(route('ai.plans.confirm-phase', $plan), ['run_all' => true])
            ->assertForbidden();
        $this->actingAs($other)->postJson(route('ai.plans.confirm-structure', $plan))
            ->assertForbidden();

        $this->assertSame(AiPlan::STATUS_PROPOSED, $plan->fresh()->status);
        $this->assertSame(0, Project::where('user_id', $other->id)->count());
    }

    public function test_stalled_chain_does_not_drift_done_chunks(): void
    {
        $user = User::factory()->create();
        $svc = new AiToolService;
        $check = $svc->validateCall('plan_propose', [
            'title' => 'Head',
            'project' => ['name' => 'کتاب', 'tasks' => []],
        ], $user);
        $this->assertTrue($check['ok']);

        // DONE head with no created projects: the book is "missing".
        $plan = AiPlan::create([
            'user_id' => $user->id,
            'title' => 'Head',
            'structure' => $check['resolved']['structure'],
            'phases' => [],
            'status' => AiPlan::STATUS_DONE,
            'current_phase' => 0,
            'executed_at' => now(),
            'expires_at' => now()->addMinutes(15),
            'idempotency_key' => bin2hex(random_bytes(16)),
        ]);
        $build = AiStudyBuild::create([
            'user_id' => $user->id,
            'title' => 'B',
            'outline_json' => [],
            'chunks_json' => [['chunk_index' => 1], ['chunk_index' => 2]],
            'plan_ids' => [$plan->id],
            'total_chunks' => 2,
            'done_chunks' => 0,
            'book_project_id' => null,
            'last_plan_id' => $plan->id,
            'status' => AiStudyBuild::STATUS_BUILDING,
            'expires_at' => now()->addDay(),
        ]);

        foreach (range(1, 2) as $i) {
            $res = StudyBuildOrchestrator::advanceIfPlanDone($plan->fresh(), $user);
            $this->assertNotNull($res, "attempt $i advanced or stalled");
            $this->assertArrayHasKey('error', $res);
        }

        // Retries stall at the same chunk: no drift, no phantom package.
        $this->assertSame(0, $build->fresh()->done_chunks);
        $this->assertSame(1, AiPlan::where('user_id', $user->id)->count());
    }
}
