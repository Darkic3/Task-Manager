<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured per-conversation working state (Phase 3, Step 2).
     *
     * History text is truncated on read (800 chars/message, bounded turn
     * budgets), so long corrections lose details. `state` keeps a compact,
     * last-write-wins snapshot instead: last intent, resolved entity ids
     * and open proposal ids for THIS conversation only.
     *
     * It is advisory context, never authorization: every id is re-resolved
     * and ownership-checked by the resolvers before any proposal/execution.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->json('state')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
