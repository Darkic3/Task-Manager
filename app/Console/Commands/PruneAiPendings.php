<?php

namespace App\Console\Commands;

use App\Models\AiPendingAction;
use Illuminate\Console\Command;

class PruneAiPendings extends Command
{
    protected $signature = 'ai:prune-pendings {--retention=30 : Days to keep terminal (executed/failed/rejected/expired) rows}';

    protected $description = 'Expire stale AI pending actions and delete old terminal rows';

    public function handle(): int
    {
        $expired = AiPendingAction::where('status', AiPendingAction::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->update(['status' => AiPendingAction::STATUS_EXPIRED]);

        $retention = max(1, (int) $this->option('retention'));
        $deleted = AiPendingAction::whereIn('status', AiPendingAction::TERMINAL_STATUSES)
            ->where('updated_at', '<', now()->subDays($retention))
            ->delete();

        $this->info("Marked {$expired} expired, deleted {$deleted} terminal rows older than {$retention}d.");

        return self::SUCCESS;
    }
}
