<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replacement link between plans (Phase 3, Step 3).
     *
     * A corrected/replacement plan must not silently stack beside — or
     * auto-retire — the old one. Cancelling with an explicit `replaced_by`
     * records the link: status stays `cancelled` (UI strings unchanged) and
     * `superseded_by_plan_id` distinguishes replaced from merely cancelled
     * or expired. Confirm/phase endpoints reject superseded plans with
     * their own code instead of the generic expiry path.
     */
    public function up(): void
    {
        Schema::table('ai_plans', function (Blueprint $table) {
            $table->foreignId('superseded_by_plan_id')
                ->nullable()
                ->after('idempotency_key')
                ->constrained('ai_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by_plan_id');
        });
    }
};
