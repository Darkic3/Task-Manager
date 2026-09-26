<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_completions', function (Blueprint $table) {
            // done | skipped | rest — existing rows are all "done".
            $table->string('status', 20)->default('done')->after('completed_at');
            // Reasons for a skip: no_time | low_energy | off_plan.
            $table->string('skip_reason', 30)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('routine_completions', function (Blueprint $table) {
            $table->dropColumn(['status', 'skip_reason']);
        });
    }
};
