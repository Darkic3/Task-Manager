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
        Schema::create('vocab_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vocab_card_id')->constrained()->onDelete('cascade');
            // Denormalized: the deck owning the card at review time.
            $table->foreignId('deck_id')->constrained('vocab_decks')->onDelete('cascade');
            $table->boolean('remembered');
            $table->unsignedTinyInteger('box_before');
            $table->unsignedTinyInteger('box_after');
            // Whether the card was prompted in reverse (Back → Front).
            $table->boolean('reversed')->default(false);
            $table->timestamp('reviewed_at')->useCurrent();

            $table->index(['user_id', 'reviewed_at']);
            $table->index('vocab_card_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vocab_reviews');
    }
};
