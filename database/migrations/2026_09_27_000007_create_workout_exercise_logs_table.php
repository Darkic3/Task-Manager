<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_exercise_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_exercise_id')->constrained()->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->string('skip_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['workout_session_id', 'workout_exercise_id'], 'wel_session_exercise_unique');
            $table->index('workout_exercise_id', 'wel_exercise_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_exercise_logs');
    }
};
