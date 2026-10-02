<?php

use App\Services\RoutineCompletionRepair;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        // One-off cleanup for completions created through the bare-tick path:
        // tracked (value/sets) routines/steps marked done with no logged
        // numbers. Enforced going forward by PlannerController::toggleRoutine.
        $stats = RoutineCompletionRepair::run();

        Log::info('routine completions repaired', $stats);
    }

    public function down(): void
    {
        // Data cleanup cannot be reversed.
    }
};
