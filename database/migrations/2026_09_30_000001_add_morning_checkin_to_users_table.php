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
            $table->boolean('morning_checkin_enabled')->default(false)->after('locale');
            $table->time('morning_window_start')->default('04:00')->after('morning_checkin_enabled');
            $table->time('morning_window_end')->default('12:00')->after('morning_window_start');
            $table->foreignId('wake_routine_id')->nullable()->after('morning_window_end')->constrained('routines')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['wake_routine_id']);
            $table->dropColumn(['morning_checkin_enabled', 'morning_window_start', 'morning_window_end', 'wake_routine_id']);
        });
    }
};
