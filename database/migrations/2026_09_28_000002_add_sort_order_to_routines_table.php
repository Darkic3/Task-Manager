<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual drag order for routines (within their time period).
     */
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('time_period');
        });
    }

    public function down(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
