<?php

namespace Tests\Feature;

use App\Models\AiPlan;
use App\Models\AiStudyBuild;
use App\Models\Project;
use App\Models\User;
use App\Services\Study\StudyBuildOrchestrator;
use App\Services\Study\StudyOutlineParser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Tests\Unit\StudyOutlineFixture;

/**
 * P0 acceptance: TRUE parallel continuation of one chain head.
 *
 * Two separate OS processes (own PDO connections) replay the confirm-phase
 * DONE branch against the same DONE head on real InnoDB row locks. Exactly
 * one worker may create the next package; the other must resurface the
 * already-proposed head (or nothing) — never a second package, never a
 * duplicate book project.
 *
 * NOTE: no RefreshDatabase here — sibling processes cannot see the test's
 * open transaction. The suite migrates the dedicated TEST database fresh
 * before and after, and cleans up in a finally block so later suites see
 * an empty database.
 */
class AiStudyChainRaceTest extends TestCase
{
    public function test_concurrent_continue_creates_single_next_package(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        try {
            $user = User::factory()->create();
            $outline = StudyOutlineParser::parse(StudyOutlineFixture::text());
            $started = StudyBuildOrchestrator::startFromOutline($user, null, $outline);
            $this->assertTrue($started['ok'] ?? false, $started['error'] ?? 'start failed');
            $head = $started['plan'];

            // Real execution of chunk 1 (builds the book project).
            $this->actingAs($user)->postJson(route('ai.plans.confirm-structure', $head))->assertOk();
            $done = $this->actingAs($user)->postJson(
                route('ai.plans.confirm-phase', $head), ['run_all' => true]
            )->assertOk();
            $autoHeadId = $done->json('next_plan.id');
            $this->assertNotNull($autoHeadId, 'auto-advance should propose chunk 2');

            // Reconstruct the exact pre-advance state: DONE head, no next
            // package yet, real book project present. (The auto-proposed
            // chunk-2 plan is an unconfirmed, item-less draft — deleting it
            // restores the lost-response scenario deterministically.)
            AiPlan::where('id', $autoHeadId)->delete();
            /** @var AiStudyBuild $build */
            $build = AiStudyBuild::firstOrFail();
            $build->update([
                'plan_ids' => [$head->id],
                'last_plan_id' => $head->id,
                'done_chunks' => 0,
                'last_error' => null,
            ]);
            $this->assertSame(1, AiPlan::where('user_id', $user->id)->count());

            // Spawn two workers, release at the same barrier.
            $tmp = sys_get_temp_dir().'/chain_race_'.getmypid();
            @mkdir($tmp, 0777, true);
            $job = ['plan_id' => $head->id, 'user_id' => $user->id, 'barrier' => $tmp.'/go'];
            file_put_contents($tmp.'/job.json', json_encode($job));
            $worker = base_path('tests/Support/chain_race_worker.php');
            $procs = [];
            foreach (['a', 'b'] as $slot) {
                $cmd = '"'.PHP_BINARY.'" "'.$worker.'" "'.$tmp.'/job.json" "'.$tmp."/out_{$slot}.json\"";
                $proc = proc_open($cmd, [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']], $pipes, base_path());
                $this->assertIsResource($proc, "worker {$slot} failed to spawn");
                $procs[$slot] = $proc;
            }
            touch($tmp.'/go');

            // Collect results (generous timeout for CI slowness).
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

            $this->assertCount(2, $results, 'both race workers must report, got: '.json_encode($results));
            foreach ($results as $slot => $r) {
                $this->assertTrue($r['ok'] ?? false, "worker {$slot} failed: ".json_encode($r));
            }

            // Exactly one worker created the package; the other resurfaced it.
            $modes = [$results['a']['mode'] ?? null, $results['b']['mode'] ?? null];
            sort($modes);
            $this->assertSame(['advanced', 'pending'], $modes, 'one advance + one resurface, got: '.json_encode($modes));
            $this->assertSame($results['a']['next_plan_id'] ?? null, $results['b']['next_plan_id'] ?? null);

            // Single next package, chain counters consistent, no duplicate book.
            $this->assertSame(2, AiPlan::where('user_id', $user->id)->count());
            $fresh = $build->fresh();
            $this->assertSame(1, $fresh->done_chunks);
            $this->assertSame((int) $results['a']['next_plan_id'], (int) $fresh->last_plan_id);
            $this->assertSame([$head->id, (int) $fresh->last_plan_id], array_map('intval', $fresh->plan_ids));
            $this->assertSame(1, Project::where('user_id', $user->id)->where('name', 'روانشناسی رشد')->count());
        } finally {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }
}
