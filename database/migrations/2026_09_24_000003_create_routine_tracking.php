<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routines', function (Blueprint $table) {
            // none = plain checkbox (current behavior), value = one number/day, sets = per-set numbers.
            $table->string('tracking_mode', 10)->default('none')->after('end_time');
            $table->string('value_kind', 20)->nullable()->after('tracking_mode');
            $table->string('value_unit', 20)->nullable()->after('value_kind');
            $table->string('value_label', 100)->nullable()->after('value_unit');
            // Cycle chain: a new cycle duplicates a routine and links back here.
            $table->foreignId('parent_id')->nullable()->after('user_id')->constrained('routines')->onDelete('set null');
            $table->unsignedSmallInteger('cycle_no')->default(1)->after('parent_id');
            $table->index(['user_id', 'tracking_mode']);
        });

        Schema::table('routine_checklist_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_sets')->default(1)->after('sort_order');
            $table->string('unit', 20)->nullable()->after('target_sets');
        });

        Schema::create('routine_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('routine_id')->constrained()->onDelete('cascade');
            $table->foreignId('checklist_item_id')->nullable()->constrained('routine_checklist_items')->onDelete('cascade');
            $table->date('completed_date');
            $table->unsignedTinyInteger('set_no')->default(1);
            $table->decimal('value', 10, 2);
            $table->timestamps();

            // NOTE: checklist_item_id is NULL for whole-routine (value mode) logs.
            // MySQL treats NULL as distinct in unique keys, so dedupe for that
            // case is enforced in code (updateOrCreate), not by this key.
            $table->unique(['routine_id', 'checklist_item_id', 'completed_date', 'set_no'], 'rl_routine_item_date_set_unique');
            $table->index(['user_id', 'routine_id', 'completed_date'], 'rl_user_routine_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_logs');
        Schema::table('routine_checklist_items', function (Blueprint $table) {
            $table->dropColumn(['target_sets', 'unit']);
        });
        Schema::table('routines', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['tracking_mode', 'value_kind', 'value_unit', 'value_label', 'parent_id', 'cycle_no']);
        });
        Schema::table('routines', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'tracking_mode']);
        });
    }
};
