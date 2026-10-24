<?php

/**
 * P4 race worker: two OS processes contend on the same plan row.
 *
 * Usage: php tests/Support/stability_race_worker.php <job.json> <result.json>
 *
 * Modes:
 * - run:      confirm-phase equivalent (plan lock + execute all phases).
 * - rollback: preview + rollbackPlan (service-level, the contended part).
 *
 * Boots the Laravel app against the TEST database in its own OS process
 * (own PDO connection) on real InnoDB row locks. The parent suite uses
 * migrate:fresh (no RefreshDatabase) so siblings see committed rows.
 */

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
use Illuminate\Support\Facades\DB;

$job = json_decode(@file_get_contents($argv[1] ?? ''), true) ?: [];
$outPath = $argv[2] ?? sys_get_temp_dir().'/stability_race_out.json';

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
    $mode = (string) ($job['mode'] ?? 'run');

    if ($mode === 'rollback') {
        $result = DB::transaction(function () use ($planId, $userId) {
            $locked = AiPlan::where('id', $planId)->lockForUpdate()->firstOrFail();
            if ((int) $locked->user_id !== $userId) {
                return ['ok' => false, 'error' => 'forbidden'];
            }
            // Hold the lock so the sibling overlaps inside the critical section.
            usleep(1000000);
            $svc = app(\App\Services\AiToolService::class);
            $res = $svc->rollbackPlan($locked->fresh(), User::findOrFail($userId));

            return ['ok' => (bool) ($res['ok'] ?? false), 'mode' => 'rollback', 'deleted' => $res['deleted'] ?? [], 'message' => $res['message'] ?? null];
        });
    } else {
        $result = DB::transaction(function () use ($planId, $userId) {
            $locked = AiPlan::where('id', $planId)->lockForUpdate()->firstOrFail();
            $user = User::findOrFail($userId);
            if ((int) $locked->user_id !== $userId
                || ! in_array($locked->status, [AiPlan::STATUS_CONFIRMED, AiPlan::STATUS_EXECUTING], true)) {
                return ['ok' => true, 'mode' => 'skipped', 'status' => $locked->status];
            }
            // Hold the lock so the sibling waits instead of slipping through.
            usleep(1000000);
            $svc = app(\App\Services\AiToolService::class);
            $ran = 0;
            while (true) {
                $fresh = $locked->fresh();
                if ($fresh->status === AiPlan::STATUS_DONE) {
                    break;
                }
                $out = $svc->executePlanPhase($fresh, $user);
                if (! ($out['ok'] ?? false)) {
                    return ['ok' => true, 'mode' => 'phase_failed', 'error' => $out['message'] ?? null];
                }
                $ran++;
                $locked = $fresh->fresh();
                if ($ran > 12) {
                    return ['ok' => false, 'error' => 'loop_guard'];
                }
            }

            return ['ok' => true, 'mode' => 'executed', 'phases' => $ran];
        });
    }
    usleep(500000);
} catch (\Throwable $e) {
    $result = ['ok' => false, 'error' => get_class($e).': '.mb_substr($e->getMessage(), 0, 300)];
}
file_put_contents($outPath, json_encode($result));
