<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('paused_seconds')->default(0)->after('duration_seconds');
            $table->timestamp('paused_at')->nullable()->after('paused_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropColumn(['paused_seconds', 'paused_at']);
        });
    }
};
