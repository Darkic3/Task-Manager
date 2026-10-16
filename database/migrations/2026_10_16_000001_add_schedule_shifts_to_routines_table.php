<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            /*
             * Postpone chain: every "move to tomorrow" appends an entry
             * {from: Y-m-d, days?: [...]} so occurrences re-anchor from that
             * date forward while history before it keeps the old schedule.
             */
            $table->json('schedule_shifts')->nullable()->after('every_n_days');
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropColumn('schedule_shifts');
        });
    }
};
