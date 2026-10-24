<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 (plan rollback): AiPlan remembers exactly what it created
     * so the user can delete the plan's items in one tap.
     * Shape: {projects: [ids], tasks: [ids], reminders: [ids],
     *          notes: [ids], routines: [ids]}. Attached (pre-existing)
     * records are never recorded here — rollback only removes what the
     * plan itself created.
     */
    public function up(): void
    {
        Schema::table('ai_plans', function (Blueprint $table) {
            $table->json('created_items')->nullable()->after('structure');
        });
    }

    public function down(): void
    {
        Schema::table('ai_plans', function (Blueprint $table) {
            $table->dropColumn('created_items');
        });
    }
};
