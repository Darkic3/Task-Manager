<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Time-of-day bucket (morning/noon/afternoon/evening/night) mirroring routines.
            $table->string('time_period')->nullable()->after('due_date');
            // Optional exact time the task is planned for.
            $table->time('due_time')->nullable()->after('time_period');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['time_period', 'due_time']);
        });
    }
};
