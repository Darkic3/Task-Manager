<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->string('section', 20)->default('main');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('target_sets')->nullable();
            $table->unsignedSmallInteger('rep_min')->nullable();
            $table->unsignedSmallInteger('rep_max')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->decimal('target_weight', 8, 2)->nullable();
            $table->decimal('target_rir', 4, 1)->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->string('tempo', 40)->nullable();
            $table->string('side_mode', 20)->nullable();
            $table->boolean('is_amrap')->default(false);
            $table->boolean('is_circuit')->default(false);
            $table->unsignedTinyInteger('circuit_rounds')->nullable();
            $table->unsignedSmallInteger('circuit_rest_seconds')->nullable();
            $table->json('alternatives')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workout_day_id', 'sort_order']);
            $table->index(['exercise_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_exercises');
    }
};
