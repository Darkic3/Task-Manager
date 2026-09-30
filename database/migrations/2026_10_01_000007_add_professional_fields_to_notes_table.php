<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Professional note facets. Purely additive: the legacy
     * category / tags / date / time columns are kept untouched so existing
     * notes stay readable while new notes use the full feature set.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('notebook_id')->nullable()->after('user_id')->constrained('notebooks')->nullOnDelete();
            $table->string('kind', 30)->default('general')->after('notebook_id')->index();
            $table->dateTime('occurred_at')->nullable()->after('date')->index();
            $table->string('visibility', 20)->default('private')->after('kind');
            $table->string('status', 20)->default('active')->after('visibility')->index();
            $table->boolean('is_pinned')->default(false)->after('is_favorite');
            $table->unsignedTinyInteger('mood')->nullable()->after('is_pinned');
            $table->unsignedTinyInteger('energy')->nullable()->after('mood');
            $table->longText('summary')->nullable()->after('content');
            $table->json('ai_metadata')->nullable()->after('summary');
            $table->foreignId('parent_id')->nullable()->after('notebook_id')->constrained('notes')->nullOnDelete();

            $table->index(['user_id', 'kind', 'occurred_at'], 'notes_user_kind_occurred_idx');
            $table->index(['user_id', 'notebook_id'], 'notes_user_notebook_idx');
            $table->index(['user_id', 'status'], 'notes_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['notebook_id']);

            $table->dropIndex('notes_user_kind_occurred_idx');
            $table->dropIndex('notes_user_notebook_idx');
            $table->dropIndex('notes_user_status_idx');
            $table->dropIndex(['kind']);

            $table->dropColumn([
                'notebook_id', 'kind', 'occurred_at', 'visibility', 'status',
                'is_pinned', 'mood', 'energy', 'summary', 'ai_metadata', 'parent_id',
            ]);
        });
    }
};