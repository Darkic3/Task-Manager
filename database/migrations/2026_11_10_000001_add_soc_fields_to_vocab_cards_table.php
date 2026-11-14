<?php

use App\Enums\CardType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vocab_cards', function (Blueprint $table) {
            $table->string('card_type', 32)->default(CardType::Definition->value)->after('back');
            $table->unsignedTinyInteger('priority')->default(2)->after('card_type');
            $table->json('tags')->nullable()->after('priority');
            $table->json('related_cards')->nullable()->after('tags');
            $table->json('extra')->nullable()->after('related_cards');
            $table->string('source_section', 64)->nullable()->after('extra');
            $table->unsignedTinyInteger('difficulty')->nullable()->after('source_section');
            $table->unsignedInteger('lapse_count')->default(0)->after('difficulty');

            $table->index('card_type');
            $table->index('priority');
            $table->index('source_section');
        });
    }

    public function down(): void
    {
        Schema::table('vocab_cards', function (Blueprint $table) {
            $table->dropIndex(['card_type']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['source_section']);

            $table->dropColumn([
                'card_type',
                'priority',
                'tags',
                'related_cards',
                'extra',
                'source_section',
                'difficulty',
                'lapse_count',
            ]);
        });
    }
};
