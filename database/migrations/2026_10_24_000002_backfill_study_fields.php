<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 1 study hardening (data only, no schema change):
     * - tasks.weight NULL → 1 (1 weight unit = 1 study page; see Task::WEIGHT_UNIT_PAGES)
     * - completed tasks with NULL completed_at → completed_at = updated_at
     *   (completed_at column was added later; old completions would otherwise
     *   vanish from TaskReportService and daily-goal counts)
     */
    public function up(): void
    {
        DB::table('tasks')->whereNull('weight')->update(['weight' => 1]);

        DB::table('tasks')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        // Data backfill is intentionally irreversible.
    }
};
