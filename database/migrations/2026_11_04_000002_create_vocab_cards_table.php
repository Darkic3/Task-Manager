<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vocab_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('deck_id')->constrained('vocab_decks')->onDelete('cascade');
            $table->string('front', 255);
            $table->text('back');
            $table->text('example')->nullable();
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('box')->default(1);
            $table->date('next_review_at');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->boolean('is_mastered')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'deck_id']);
            $table->index(['user_id', 'next_review_at']);
            $table->index(['user_id', 'box']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vocab_cards');
    }
};
