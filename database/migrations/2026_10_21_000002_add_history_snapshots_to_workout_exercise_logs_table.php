<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep per-exercise history readable after the plan template changes:
     * each log remembers which library exercise it was and under which
     * target, even if the template row (or the library exercise) is gone.
     */
    public function up(): void
    {
        Schema::table('workout_exercise_logs', function (Blueprint $table): void {
            $table->foreignId('exercise_id')->nullable()->after('workout_exercise_id')
                ->constrained('exercises')->nullOnDelete();
            $table->string('exercise_name')->nullable()->after('exercise_id');
            $table->string('target_label', 120)->nullable()->after('exercise_name');
        });

        Schema::table('workout_exercise_logs', function (Blueprint $table): void {
            $table->dropForeign(['workout_exercise_id']);
        });
        DB::statement('ALTER TABLE workout_exercise_logs MODIFY workout_exercise_id BIGINT UNSIGNED NULL');
        Schema::table('workout_exercise_logs', function (Blueprint $table): void {
            $table->foreign('workout_exercise_id')->references('id')->on('workout_exercises')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercise_logs', function (Blueprint $table): void {
            $table->dropForeign(['workout_exercise_id']);
        });
        DB::statement('ALTER TABLE workout_exercise_logs MODIFY workout_exercise_id BIGINT UNSIGNED NOT NULL');
        Schema::table('workout_exercise_logs', function (Blueprint $table): void {
            $table->foreign('workout_exercise_id')->references('id')->on('workout_exercises')->cascadeOnDelete();
        });

        Schema::table('workout_exercise_logs', function (Blueprint $table): void {
            $table->dropForeign(['exercise_id']);
            $table->dropColumn(['exercise_id', 'exercise_name', 'target_label']);
        });
    }
};
