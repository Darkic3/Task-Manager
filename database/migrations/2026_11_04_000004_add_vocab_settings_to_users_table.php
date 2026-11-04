<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('vocab_daily_limit')->default(20)->after('wake_routine_id');
            $table->unsignedTinyInteger('vocab_session_limit')->default(20)->after('vocab_daily_limit');
            $table->boolean('vocab_remind_enabled')->default(true)->after('vocab_session_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vocab_daily_limit', 'vocab_session_limit', 'vocab_remind_enabled']);
        });
    }
};
