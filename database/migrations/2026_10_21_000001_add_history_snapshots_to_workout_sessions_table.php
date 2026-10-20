<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make workout history immutable: sessions keep working (and reports
     * keep showing them) even after their plan/day template rows are
     * edited or deleted. Snapshots are filled when a session starts.
     */
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreignId('workout_plan_id')->nullable()->after('user_id')
                ->constrained('workout_plans')->nullOnDelete();
            $table->string('plan_title')->nullable()->after('workout_plan_id');
            $table->string('day_title')->nullable()->after('plan_title');
            $table->string('weekday', 10)->nullable()->after('day_title');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropForeign(['workout_day_id']);
        });
        DB::statement('ALTER TABLE workout_sessions MODIFY workout_day_id BIGINT UNSIGNED NULL');
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreign('workout_day_id')->references('id')->on('workout_days')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropForeign(['workout_day_id']);
        });
        DB::statement('ALTER TABLE workout_sessions MODIFY workout_day_id BIGINT UNSIGNED NOT NULL');
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->foreign('workout_day_id')->references('id')->on('workout_days')->cascadeOnDelete();
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropForeign(['workout_plan_id']);
            $table->dropColumn(['workout_plan_id', 'plan_title', 'day_title', 'weekday']);
        });
    }
};
