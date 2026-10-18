<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->decimal('bodyweight_kg', 8, 2)->nullable()->after('pain_note');
            $table->timestamp('bodyweight_recorded_at')->nullable()->after('bodyweight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropColumn(['bodyweight_kg', 'bodyweight_recorded_at']);
        });
    }
};
