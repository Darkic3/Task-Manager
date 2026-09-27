<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('workout_plans')->nullOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('week_number')->nullable();
            $table->unsignedSmallInteger('cycle_no')->default(1);
            $table->string('goal')->nullable();
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_plans');
    }
};
