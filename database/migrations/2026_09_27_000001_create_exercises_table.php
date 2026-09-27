<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->json('aliases')->nullable();
            $table->string('category', 40)->nullable();
            $table->json('muscle_groups')->nullable();
            $table->json('equipment')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'normalized_name']);
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
