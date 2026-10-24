<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_study_builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->onDelete('cascade');
            $table->string('title', 255);
            // Parsed outline (book/chapters/lessons/topics) + chunker output.
            $table->json('outline_json');
            $table->json('chunks_json');
            // Every chunk plan id, in order (idempotency + retry safety).
            $table->json('plan_ids')->nullable();
            $table->unsignedTinyInteger('total_chunks')->default(1);
            $table->unsignedTinyInteger('done_chunks')->default(0);
            // Real book project id, known after chunk 1 executes.
            $table->foreignId('book_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('last_plan_id')->nullable()->constrained('ai_plans')->nullOnDelete();
            $table->string('status', 20)->default('building');
            $table->string('last_error', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_study_builds');
    }
};
