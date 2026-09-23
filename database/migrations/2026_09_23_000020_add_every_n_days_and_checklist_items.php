<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* Routine frequency: every N days (e.g., every 2 days = every other day) */
        Schema::table('routines', function (Blueprint $table) {
            $table->unsignedSmallInteger('every_n_days')->nullable()->after('month_days');
        });

        /* Persistent checklist items per routine (e.g., "Set 1", "Set 2") */
        Schema::create('routine_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['routine_id', 'sort_order']);
        });

        /* Per-item per-date completion (mirrors routine_completions pattern) */
        Schema::create('routine_checkitem_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained('routine_checklist_items')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('completed_date');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['checklist_item_id', 'completed_date'], 'rcc_item_date_unique');
            $table->index(['user_id', 'completed_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_checkitem_completions');
        Schema::dropIfExists('routine_checklist_items');
        Schema::table('routines', function (Blueprint $table) {
            $table->dropColumn('every_n_days');
        });
    }
};
