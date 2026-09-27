<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_plan_id')->constrained()->cascadeOnDelete();
            $table->string('weekday', 10);
            $table->string('title');
            $table->string('type', 20)->default('rest');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workout_plan_id', 'weekday']);
            $table->index(['workout_plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_days');
    }
};
