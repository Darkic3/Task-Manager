<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved, reusable selections of notes that can be attached to any AI
     * conversation (Phase 2).
     */
    public function up(): void
    {
        Schema::create('note_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('note_count')->default(0);
            $table->unsignedInteger('char_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'ai_conversation_id']);
        });

        Schema::create('note_share_note', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_share_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['note_share_id', 'note_id'], 'note_share_note_unique');
            $table->index('note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_share_note');
        Schema::dropIfExists('note_shares');
    }
};