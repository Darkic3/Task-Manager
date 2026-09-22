<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_completions', function (Blueprint $table) {
            $table->dateTime('completed_at')->nullable()->after('completed_date');
        });
    }

    public function down(): void
    {
        Schema::table('routine_completions', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
