<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['user_id', 'due_date'], 'tasks_user_due_index');
            $table->index(['user_id', 'status'], 'tasks_user_status_index');
            $table->index(['user_id', 'due_date', 'status'], 'tasks_user_due_status_index');
            $table->index(['project_id', 'status'], 'tasks_project_status_index');
            $table->index('parent_id', 'tasks_parent_index');
        });

        Schema::table('routines', function (Blueprint $table) {
            $table->index(['user_id', 'frequency'], 'routines_user_frequency_index');
        });

        Schema::table('routine_completions', function (Blueprint $table) {
            $table->index(['user_id', 'routine_id', 'completed_date'], 'rc_user_routine_date_index');
        });

        Schema::table('routine_checkitem_completions', function (Blueprint $table) {
            $table->index(['checklist_item_id', 'completed_date'], 'rcc_item_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_due_index');
            $table->dropIndex('tasks_user_status_index');
            $table->dropIndex('tasks_user_due_status_index');
            $table->dropIndex('tasks_project_status_index');
            $table->dropIndex('tasks_parent_index');
        });

        Schema::table('routines', function (Blueprint $table) {
            $table->dropIndex('routines_user_frequency_index');
        });

        Schema::table('routine_completions', function (Blueprint $table) {
            $table->dropIndex('rc_user_routine_date_index');
        });

        Schema::table('routine_checkitem_completions', function (Blueprint $table) {
            $table->dropIndex('rcc_item_date_index');
        });
    }
};
