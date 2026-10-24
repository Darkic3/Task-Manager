<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4 study/report hot paths:
     * - tasks(user_id, status, completed_at): TaskReportService window
     *   queries + Project::dailyGoalProgress/History completed-task lookups.
     * - time_entries(user_id, status, started_at): StudyReportService and
     *   daily-goal minutes aggregation.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'completed_at'], 'tasks_user_status_completed_index');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'started_at'], 'te_user_status_started_index');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex('te_user_status_started_index');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_status_completed_index');
        });
    }
};
