<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_day_id')->constrained()->cascadeOnDelete();
            $table->date('workout_date');
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('session_note')->nullable();
            $table->text('pain_note')->nullable();
            $table->timestamps();

            $table->unique(['workout_day_id', 'workout_date']);
            $table->index(['user_id', 'workout_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};
