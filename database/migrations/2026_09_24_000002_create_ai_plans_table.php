<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->onDelete('cascade');
            $table->string('title', 255);
            // Validated tree: {project:{...}, subprojects:[{name,tasks:[{title,due_date,priority,subtasks:[...]}]}]}
            $table->json('structure');
            // [{key,label,total,done,status,result_ids[]}] — server advances current_phase.
            $table->json('phases');
            $table->string('status', 20)->default('proposed');
            $table->unsignedTinyInteger('current_phase')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('executed_at')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_plans');
    }
};
