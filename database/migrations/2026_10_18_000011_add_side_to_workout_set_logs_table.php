<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_set_logs', function (Blueprint $table): void {
            $table->string('side', 10)->default('both')->after('set_number');

            // Old unique is only (workout_exercise_log_id, set_number).
            // With per-side logging we need (workout_exercise_log_id, set_number, side).
            $table->dropUnique('wsl_exercise_set_unique');
            $table->unique(['workout_exercise_log_id', 'set_number', 'side'], 'wsl_exercise_set_side_unique');
        });

        // Ensure any existing NULLs (if DB didn't backfill defaults) are fixed.
        // (MySQL usually backfills defaults, but make it explicit.)
        DB::table('workout_set_logs')->whereNull('side')->update(['side' => 'both']);
    }

    public function down(): void
    {
        Schema::table('workout_set_logs', function (Blueprint $table): void {
            $table->dropUnique('wsl_exercise_set_side_unique');
            $table->dropColumn('side');
            $table->unique(['workout_exercise_log_id', 'set_number'], 'wsl_exercise_set_unique');
        });
    }
};
