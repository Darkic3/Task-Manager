<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            // build = do the habit, avoid = must NOT do it (forbidden habit).
            $table->string('behavior_type', 10)->default('build')->after('frequency');
            // When true, each slip log carries a quantity (e.g. cigarettes count).
            $table->boolean('count_violations')->default(false)->after('behavior_type');
        });

        // One row per slip event. Step-level slips reference a checklist item,
        // routine-level slips leave checklist_item_id null (day is lost at once).
        Schema::create('routine_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->onDelete('cascade');
            $table->foreignId('checklist_item_id')->nullable()->constrained('routine_checklist_items')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->dateTime('occurred_at');
            $table->date('occurred_date');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('trigger', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['routine_id', 'occurred_date']);
            $table->index(['user_id', 'occurred_date']);
        });

        // Cravings + free notes with exact timestamps, usable in reports.
        Schema::create('routine_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routine_id')->constrained()->onDelete('cascade');
            $table->foreignId('checklist_item_id')->nullable()->constrained('routine_checklist_items')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('kind', 10)->default('note'); // craving | note
            $table->dateTime('occurred_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['routine_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_notes');
        Schema::dropIfExists('routine_violations');
        Schema::table('routines', function (Blueprint $table) {
            $table->dropColumn(['behavior_type', 'count_violations']);
        });
    }
};
