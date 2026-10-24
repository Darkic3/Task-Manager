<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P1 Master Plan versioning on the existing ai_study_builds row.
     *
     * Why columns, not a new ai_study_build_versions table: approve/revise
     * must serialize on the SAME row lock the chain already uses
     * (lockForUpdate in advanceIfPlanDone/pendingNextPlan). A second table
     * would need its own lock ordering and join logic for zero product
     * gain — version history is small (cap 10) and read together with the
     * build in every master-card render.
     *
     * Backfill: chains created before P1 never had explicit master
     * approval, so blocking them now would strand in-flight P0 chains.
     * They are grandfathered as approved v1 instead.
     */
    public function up(): void
    {
        Schema::table('ai_study_builds', function (Blueprint $table) {
            $table->unsignedTinyInteger('version')->default(1)->after('total_chunks');
            $table->unsignedTinyInteger('approved_version')->nullable()->after('version');
            $table->timestamp('approved_at')->nullable()->after('approved_version');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            $table->json('versions')->nullable()->after('approved_by');
        });

        DB::table('ai_study_builds')
            ->whereNull('approved_version')
            ->update([
                'approved_version' => DB::raw('version'),
                'approved_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('ai_study_builds', function (Blueprint $table) {
            $table->dropColumn(['version', 'approved_version', 'approved_at', 'approved_by', 'versions']);
        });
    }
};
