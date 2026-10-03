<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_violations', function (Blueprint $table) {
            // 1-10 craving/mood intensity, nullable for backward compat
            $table->unsignedTinyInteger('mood')->nullable()->after('quantity');
            // Free location label (home, work, with friends...), nullable
            $table->string('location', 100)->nullable()->after('mood');
        });

        Schema::table('routine_notes', function (Blueprint $table) {
            $table->unsignedTinyInteger('mood')->nullable()->after('kind');
            $table->string('location', 100)->nullable()->after('mood');
            // craving notes can also carry a trigger
            $table->string('trigger', 100)->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('routine_violations', function (Blueprint $table) {
            $table->dropColumn(['mood', 'location']);
        });
        Schema::table('routine_notes', function (Blueprint $table) {
            $table->dropColumn(['mood', 'location', 'trigger']);
        });
    }
};
