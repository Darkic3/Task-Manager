<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-project daily minimum ("چند قدم در روز") with a selectable metric.
     * - daily_goal_value: null = disabled, otherwise the required amount per day
     * - daily_goal_unit: tasks (= قدم / completed tasks), pages (= weight sum), minutes (= focused time)
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('daily_goal_value')->nullable()->after('budget');
            $table->string('daily_goal_unit', 20)->nullable()->default('tasks')->after('daily_goal_value');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['daily_goal_value', 'daily_goal_unit']);
        });
    }
};
