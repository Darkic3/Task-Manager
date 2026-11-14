<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocab_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('vocab_cards')->cascadeOnDelete();
            $table->foreignId('related_card_id')->constrained('vocab_cards')->cascadeOnDelete();
            $table->string('relation_type', 32)->default('related');
            $table->timestamps();

            $table->unique(['card_id', 'related_card_id', 'relation_type']);
            $table->index('card_id');
            $table->index('related_card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vocab_relations');
    }
};
