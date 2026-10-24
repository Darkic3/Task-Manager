<?php

/**
 * Chain-race worker for AiStudyChainRaceTest.
 *
 * Usage: php tests/Support/chain_race_worker.php <job.json> <result.json>
 *
 * Boots the Laravel app against the TEST database in its own OS process
 * (own PDO connection) and replays the confirm-phase DONE branch:
 * plan lock -> pendingNextPlan -> advanceIfPlanDone, inside ONE database
 * transaction. Two workers released at the same barrier therefore contend
 * on real InnoDB row locks — this is a true parallelism test, not a
 * sequential simulation.
 */

// Test-database environment (mirrors phpunit.xml). Set before boot.
foreach ([
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'task_test',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'AI_LOG_CHANNEL' => 'ai_memory',
] as $k => $v) {
    putenv($k.'='.$v);
    $_ENV[$k] = $v;
    $_SERVER[$k] = $v;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiPlan;
use App\Models\User;
use App\Services\Study\StudyBuildOrchestrator;
use Illuminate\Support\Facades\DB;

$job = json_decode(@file_get_contents($argv[1] ?? ''), true) ?: [];
$outPath = $argv[2] ?? sys_get_temp_dir().'/chain_race_out.json';

// Barrier: both workers start their transactions at (almost) the same time.
$deadline = microtime(true) + 30;
while (! isset($job['barrier']) || ! file_exists($job['barrier'])) {
    if (microtime(true) >= $deadline) {
        file_put_contents($outPath, json_encode(['ok' => false, 'error' => 'barrier_timeout']));
        exit(0);
    }
    usleep(50000);
}

$result = ['ok' => false, 'error' => 'unknown'];
try {
    $planId = (int) ($job['plan_id'] ?? 0);
    $userId = (int) ($job['user_id'] ?? 0);
    DB::transaction(function () use ($planId, $userId, &$result) {
        $locked = AiPlan::where('id', $planId)->lockForUpdate()->firstOrFail();
        $user = User::findOrFail($userId);

        $pending = StudyBuildOrchestrator::pendingNextPlan($locked->fresh(), $user);
        if ($pending) {
            $result = ['ok' => true, 'mode' => 'pending', 'next_plan_id' => $pending->id];
        } else {
            // Hold the row locks briefly so the sibling worker is forced to
            // wait on them instead of slipping through on timing luck.
            usleep(1500000);
            $adv = StudyBuildOrchestrator::advanceIfPlanDone($locked->fresh(), $user);
            if ($adv === null) {
                $result = ['ok' => true, 'mode' => 'none'];
            } elseif (! empty($adv['next_plan'])) {
                $result = ['ok' => true, 'mode' => 'advanced', 'next_plan_id' => $adv['next_plan']->id];
            } else {
                $result = ['ok' => true, 'mode' => 'finished_or_error'];
            }
        }
        // Keep the transaction (and its locks) open a moment longer.
        usleep(1500000);
    });
} catch (\Throwable $e) {
    $result = ['ok' => false, 'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 500)];
}

file_put_contents($outPath, json_encode($result));
