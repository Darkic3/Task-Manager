<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_checklist_items', function (Blueprint $table) {
            // Time-of-day bucket (morning/noon/afternoon/evening/night), mirroring routines.
            $table->string('time_period')->nullable()->after('name');
            // Optional exact time the step is planned for. Mutually exclusive with time_period.
            $table->time('scheduled_time')->nullable()->after('time_period');
        });
    }

    public function down(): void
    {
        Schema::table('routine_checklist_items', function (Blueprint $table) {
            $table->dropColumn(['time_period', 'scheduled_time']);
        });
    }
};
