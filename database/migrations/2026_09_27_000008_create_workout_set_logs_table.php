<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_set_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_exercise_log_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->decimal('reps', 8, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('rir', 4, 1)->nullable();
            $table->unsignedTinyInteger('form_rating')->nullable();
            $table->unsignedTinyInteger('pain_level')->nullable();
            $table->boolean('completed')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['workout_exercise_log_id', 'set_number'], 'wsl_exercise_set_unique');
            $table->index(['workout_exercise_log_id', 'completed'], 'wsl_exercise_completed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_set_logs');
    }
};
