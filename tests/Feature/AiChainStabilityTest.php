<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\AiProviderCall;
use App\Models\AiSetting;
use App\Models\AiStudyBuild;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\AiLogger;
use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * P4 chain stability: stop vs cancel vs rollback, recovery, idempotency,
 * real-concurrency races, expiry + reissue, and log hygiene.
 *
 * Sequential tests use RefreshDatabase. The two race tests spawn real OS
 * processes (own DB connections) on real InnoDB locks — see
 * tests/Support/stability_race_worker.php — with migrate:fresh isolation.
 */
class AiChainStabilityTest extends TestCase
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

    private function rollbackWithPreview(User $user, AiPlan $plan)
    {
        $preview = $this->actingAs($user)->getJson(route('ai.plans.rollback-preview', $plan))->assertOk();

        return $this->actingAs($user)->postJson(route('ai.plans.rollback', $plan), [
            'preview_token' => $preview->json('preview_token'),
        ]);
    }

    private function spawnRaceWorkers(array $job, string $worker = 'stability_race_worker.php'): array
    {
        $tmp = sys_get_temp_dir().'/stability_race_'.getmypid().'_'.random_int(1000, 9999);
        @mkdir($tmp, 0777, true);
        $job['barrier'] = $tmp.'/go';
        file_put_contents($tmp.'/job.json', json_encode($job));
        $workerPath = base_path('tests/Support/'.$worker);
        $procs = [];
        foreach (['a', 'b'] as $slot) {
            $cmd = '"'.PHP_BINARY.'" "'.$workerPath.'" "'.$tmp.'/job.json" "'.$tmp."/out_{$slot}.json\"";
            $proc = proc_open($cmd, [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $pipes, base_path());
            $this->assertIsResource($proc, "worker {$slot} failed to spawn");
            $procs[$slot] = $proc;
        }
        touch($tmp.'/go');

        $results = [];
        $deadline = microtime(true) + 120;
        while (count($results) < 2 && microtime(true) < $deadline) {
            foreach (['a', 'b'] as $slot) {
                if (! isset($results[$slot]) && file_exists($tmp."/out_{$slot}.json")) {
                    $results[$slot] = json_decode(file_get_contents($tmp."/out_{$slot}.json"), true);
                }
            }
            usleep(100000);
        }
        foreach ($procs as $proc) {
            proc_terminate($proc);
            proc_close($proc);
        }
        $this->assertCount(2, $results, 'both race workers must report: '.json_encode($results));

        return $results;
    }

    public function test_state_transition_table_is_complete_and_consistent(): void
    {
        $map = AiPlan::TRANSITIONS;
        $all = [AiPlan::STATUS_PROPOSED, AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING, AiPlan::STATUS_DONE, AiPlan::STATUS_CANCELLED, AiPlan::STATUS_EXPIRED, AiPlan::STATUS_FAILED, AiPlan::STATUS_OBSOLETE];
        $this->assertSame([], array_diff($all, array_keys($map)));

        // Terminal states have no outgoing transitions; live states do.
        foreach ([AiPlan::STATUS_DONE, AiPlan::STATUS_CANCELLED, AiPlan::STATUS_OBSOLETE, AiPlan::STATUS_EXPIRED] as $terminal) {
            $this->assertSame([], $map[$terminal], $terminal.' must be terminal');
        }
        foreach ([AiPlan::STATUS_PROPOSED, AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING, AiPlan::STATUS_FAILED] as $live) {
            $this->assertNotEmpty($map[$live], $live.' must have outgoing transitions');
        }
        // isActionable() covers exactly the states with outgoing work.
        $actionable = [AiPlan::STATUS_PROPOSED, AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING, AiPlan::STATUS_FAILED];
        foreach ($all as $status) {
            $plan = new AiPlan(['status' => $status, 'expires_at' => now()->addHour()]);
            $this->assertSame(in_array($status, $actionable, true), $plan->isActionable(), $status);
        }
    }

    public function test_cancel_pending_chain_stops_everything(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);

        $this->actingAs($user)->postJson(route('ai.plans.cancel', $started['plan']))->assertOk();

        $build = AiStudyBuild::firstOrFail();
        $this->assertSame(AiStudyBuild::STATUS_CANCELLED, $build->status);
        $this->assertSame(0, $build->done_chunks);
        $this->assertSame(AiPlan::STATUS_CANCELLED, $started['plan']->fresh()->status);
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_no_package_created_after_cancel(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $done = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $head = AiPlan::findOrFail($done->json('next_plan.id'));

        $this->actingAs($user)->postJson(route('ai.plans.cancel', $head))->assertOk();
        $this->assertSame(AiStudyBuild::STATUS_CANCELLED, AiStudyBuild::firstOrFail()->status);

        // The DONE head reports deduped with NO next package — the chain is frozen.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), [])
            ->assertOk()->assertJsonPath('deduped', true)->assertJsonMissingPath('next_plan');
        $this->assertSame(2, AiPlan::where('user_id', $user->id)->count());
    }

    public function test_repeat_cancel_is_idempotent(): void
    {
        $user = User::factory()->create();
        $plan = $this->startChain($user)['plan'];

        $this->actingAs($user)->postJson(route('ai.plans.cancel', $plan))->assertOk()->assertJsonMissingPath('code');
        $this->actingAs($user)->postJson(route('ai.plans.cancel', $plan))->assertOk()->assertJsonPath('deduped', true);

        $this->assertSame(AiStudyBuild::STATUS_CANCELLED, AiStudyBuild::firstOrFail()->status);
        $this->assertSame(0, Project::where('user_id', $user->id)->count());
    }

    public function test_recovery_after_partial_crash_finds_orphan_plan(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $build = $started['build']->fresh();
        $this->approve($user, $build);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $done = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $pkg2 = AiPlan::findOrFail($done->json('next_plan.id'));

        // Simulate a crash: chunk-3 plan row committed, chain pointer NOT updated.
        $chunks = $build->fresh()->chunks_json;
        $bookId = Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->firstOrFail()->id;
        $svc = new \App\Services\AiToolService;
        $check = $svc->validateCall('plan_propose', StudyBuildOrchestrator::buildPlanArgs($chunks[2], $bookId), $user);
        $this->assertTrue($check['ok'], $check['error'] ?? 'validate failed');
        $stored = app(\App\Http\Controllers\AiPlanController::class)->storeResolvedPlan($user->id, null, $check['resolved']);
        $orphanId = $stored['plan']->id;
        $this->assertCount(3, AiPlan::where('user_id', $user->id)->pluck('id')->all());

        // Execute pkg2 → advance re-derives the same key and REUSES the orphan.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $pkg2))->assertOk();
        $fin = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        $this->assertSame($orphanId, (int) $fin->json('next_plan.id'));
        $this->assertSame(3, AiPlan::where('user_id', $user->id)->count());
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
    }

    public function test_failed_retry_is_idempotent(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $done = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $pkg2 = AiPlan::findOrFail($done->json('next_plan.id'));
        $tasksBefore = Task::where('user_id', $user->id)->count();

        // Delete the shared book mid-chain → attach fails honestly.
        Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->delete();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $pkg2))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'phase_failed');

        $this->assertSame(AiPlan::STATUS_FAILED, $pkg2->fresh()->status);
        $this->assertNotEmpty($pkg2->fresh()->last_error);
        $pointer = $pkg2->fresh()->current_phase;

        // A doomed retry changes nothing: same failure, no duplicates, same pointer.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'phase_failed');
        $this->assertSame(AiPlan::STATUS_FAILED, $pkg2->fresh()->status);
        $this->assertSame($pointer, $pkg2->fresh()->current_phase);
        $this->assertSame($tasksBefore, Task::where('user_id', $user->id)->count());
    }

    public function test_concurrent_execution_runs_exactly_once(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        try {
            $user = User::factory()->create();
            $started = $this->startChain($user);
            $this->approve($user, $started['build']);
            $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();

            $expectedTasks = $started['plan']->fresh()->structure['projects'][0]['tasks'] ?? [];
            $results = $this->spawnRaceWorkers([
                'mode' => 'run',
                'plan_id' => $started['plan']->id,
                'user_id' => $user->id,
            ]);

            $modes = [($results['a']['mode'] ?? null), ($results['b']['mode'] ?? null)];
            sort($modes);
            $this->assertSame(['executed', 'skipped'], $modes, json_encode($results));

            $plan = $started['plan']->fresh();
            $this->assertSame(AiPlan::STATUS_DONE, $plan->status);
            // Chunk 1 = book + 3 chapter subprojects + their lessons: counted once.
            $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
            $this->assertSame(3, Project::where('user_id', $user->id)->whereNotNull('parent_id')->count());
        } finally {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    public function test_concurrent_continue_proposes_single_next_package(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        try {
            $user = User::factory()->create();
            $started = $this->startChain($user);
            $this->approve($user, $started['build']);
            $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
            $done = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
            $autoId = $done->json('next_plan.id');
            $this->assertNotNull($autoId);

            // Lost-response state: auto head removed, chain pointer rewound.
            AiPlan::where('id', $autoId)->delete();
            $build = AiStudyBuild::firstOrFail();
            $build->update(['plan_ids' => [$started['plan']->id], 'last_plan_id' => $started['plan']->id, 'done_chunks' => 0, 'last_error' => null]);

            $results = $this->spawnRaceWorkers([
                'mode' => 'continue',
                'plan_id' => $started['plan']->id,
                'user_id' => $user->id,
            ], 'chain_race_worker.php');

            $modes = [($results['a']['mode'] ?? null), ($results['b']['mode'] ?? null)];
            sort($modes);
            $this->assertSame(['advanced', 'pending'], $modes, json_encode($results));
            $this->assertSame($results['a']['next_plan_id'] ?? null, $results['b']['next_plan_id'] ?? null);
            $this->assertSame(2, AiPlan::where('user_id', $user->id)->count());
            $this->assertSame(1, $build->fresh()->done_chunks);
        } finally {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    public function test_concurrent_rollbacks_stay_safe(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        try {
            $user = User::factory()->create();
            $started = $this->startChain($user);
            $this->approve($user, $started['build']);
            $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
            $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])
                ->assertOk()->assertJsonPath('plan.status', 'done');

            $expectedTasks = Task::where('user_id', $user->id)->count();
            $this->assertGreaterThan(0, $expectedTasks);

            $results = $this->spawnRaceWorkers([
                'mode' => 'rollback',
                'plan_id' => $started['plan']->id,
                'user_id' => $user->id,
            ]);

            $this->assertTrue($results['a']['ok'] ?? false, json_encode($results['a']));
            $this->assertTrue($results['b']['ok'] ?? false, json_encode($results['b']));
            $deletedSum = (int) (($results['a']['deleted']['tasks'] ?? 0)) + (int) (($results['b']['deleted']['tasks'] ?? 0));
            $this->assertSame($expectedTasks, $deletedSum, 'exactly-once deletion across both workers');
            $this->assertSame(0, Task::where('user_id', $user->id)->count());
            $this->assertSame([], $started['plan']->fresh()->created_items);
        } finally {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }

    public function test_rollback_cross_user_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $started = $this->startChain($owner);
        $this->approve($owner, $started['build']);
        $this->actingAs($owner)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($owner)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();

        $this->actingAs($other)->getJson(route('ai.plans.rollback-preview', $started['plan']))->assertForbidden();
        $this->actingAs($other)->postJson(route('ai.plans.rollback', $started['plan']), ['preview_token' => 'x'])->assertForbidden();

        $this->assertNotSame([], $started['plan']->fresh()->created_items);
    }

    public function test_unrelated_data_survives_rollback(): void
    {
        $user = User::factory()->create();
        $sameName = Project::create(['user_id' => $user->id, 'name' => 'روانشناسی رشد', 'status' => 'in_progress', 'type' => 'project', 'sort_order' => 0]);
        $keeper = Project::create(['user_id' => $user->id, 'name' => 'بایگانی شخصی', 'status' => 'in_progress', 'type' => 'project', 'sort_order' => 1]);

        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();

        $this->rollbackWithPreview($user, $started['plan'])->assertOk();

        // Same-name AND attached pre-existing projects are untouched; only the ledger dies.
        $this->assertNotNull(Project::find($sameName->id));
        $this->assertNotNull(Project::find($keeper->id));
        $this->assertSame([], $started['plan']->fresh()->created_items);
    }

    public function test_rollback_after_preview_and_confirm(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();

        $preview = $this->actingAs($user)->getJson(route('ai.plans.rollback-preview', $started['plan']))->assertOk();
        $this->assertGreaterThan(0, (int) $preview->json('would_delete.tasks'));
        $this->assertGreaterThan(0, (int) $preview->json('would_delete.projects'));
        $this->assertNotEmpty($preview->json('preview_token'));

        $res = $this->actingAs($user)->postJson(route('ai.plans.rollback', $started['plan']), [
            'preview_token' => $preview->json('preview_token'),
        ])->assertOk();

        $this->assertSame((int) $preview->json('would_delete.tasks'), (int) $res->json('deleted.tasks'));
        $this->assertSame((int) $preview->json('would_delete.projects'), (int) $res->json('deleted.projects'));
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_stale_preview_rejected_after_ledger_change(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        // Execute only the first phase: partial ledger.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['phase' => 0])->assertOk();

        $preview = $this->actingAs($user)->getJson(route('ai.plans.rollback-preview', $started['plan']))->assertOk();

        // Ledger grows (another phase runs) → the old token is stale.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.rollback', $started['plan']), [
            'preview_token' => $preview->json('preview_token'),
        ])->assertStatus(409)->assertJsonPath('code', 'stale_preview');

        // Fresh preview + confirm works on the final ledger.
        $this->rollbackWithPreview($user, $started['plan'])->assertOk();
        $this->assertSame(0, Task::where('user_id', $user->id)->count());
    }

    public function test_expiry_mid_chain_and_reissue(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $done = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $pkg2 = AiPlan::findOrFail($done->json('next_plan.id'));

        // Head expires mid-chain: execution stops, nothing is corrupted.
        $pkg2->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $pkg2))->assertStatus(422);
        $this->assertSame(AiStudyBuild::STATUS_BUILDING, AiStudyBuild::firstOrFail()->status);
        $this->assertSame($pkg2->id, (int) AiStudyBuild::firstOrFail()->last_plan_id);

        // Reissue from the approved master: same position, fresh proposal.
        $re = $this->actingAs($user)->postJson(route('ai.plans.reissue', $pkg2))->assertOk();
        $this->assertSame(2, (int) $re->json('plan.package_index'));
        $this->assertNotSame($pkg2->id, (int) $re->json('plan.id'));
        $newHead = AiPlan::findOrFail($re->json('plan.id'));

        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $newHead))->assertOk();
        $fin = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $newHead), ['run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');
        $this->assertNotNull($fin->json('next_plan.id'));
        $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
    }

    public function test_done_and_superseded_behavior_preserved(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])
            ->assertOk()->assertJsonPath('plan.status', 'done');

        // Done stays deduped; rollback still works with preview.
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])
            ->assertOk()->assertJsonPath('deduped', true);
        $this->rollbackWithPreview($user, $started['plan'])->assertOk();
    }

    public function test_refresh_recovers_cancelled_and_failed_state(): void
    {
        $user = User::factory()->create();
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $done = $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $pkg2 = AiPlan::findOrFail($done->json('next_plan.id'));

        Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->delete();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $pkg2))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $pkg2), ['run_all' => true])
            ->assertStatus(422)->assertJsonPath('code', 'phase_failed');

        // Simulated reload: server state shows the failed package + error.
        $show = $this->actingAs($user)->getJson(route('ai.study-builds.show', $started['build']))->assertOk();
        $byIndex = collect($show->json('packages'))->keyBy('package_index');
        $this->assertSame('failed', $byIndex[2]['status']);
        $this->assertNotEmpty($byIndex[2]['last_error']);
        $this->assertContains('execute', $byIndex[2]['actions']);

        // Cancel the chain, reload: cancelled everywhere, built data stays.
        $this->actingAs($user)->postJson(route('ai.plans.cancel', $pkg2))->assertOk();
        $show2 = $this->actingAs($user)->getJson(route('ai.study-builds.show', $started['build']))->assertOk();
        $this->assertSame(AiStudyBuild::STATUS_CANCELLED, $show2->json('master.status'));
        $this->assertSame(AiStudyBuild::STATUS_CANCELLED, $show2->json('study_build.status'));
    }

    public function test_no_secrets_in_logs(): void
    {
        $user = User::factory()->create();
        AiSetting::create([
            'user_id' => $user->id, 'default_provider' => 'openai',
            'openai_key' => 'sk-test-SECRETKEY123', 'openai_model' => 'gpt-4o-mini',
        ]);
        $started = $this->startChain($user);
        $this->approve($user, $started['build']);
        $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $started['plan']))->assertOk();
        $this->actingAs($user)->postJson(route('ai.plans.confirm-phase', $started['plan']), ['run_all' => true])->assertOk();
        $this->rollbackWithPreview($user, $started['plan'])->assertOk();

        $tail = implode("\n", AiLogger::tailForUser($user->id, 120));
        $this->assertStringContainsString('phase_executed', $tail);
        $this->assertStringContainsString('rolled_back', $tail);
        $this->assertStringNotContainsString('sk-test-SECRETKEY123', $tail);
        $this->assertStringNotContainsString('Bearer', $tail);
        $this->assertStringNotContainsString('api_key', strtolower($tail));
    }
}
