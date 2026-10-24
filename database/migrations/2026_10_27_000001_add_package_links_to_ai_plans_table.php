<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2 package identity on ai_plans (additive, nullable — no rewrite).
     *
     * Why columns, not derivation: a package must carry a STABLE link to
     * (build, master version, position) so that (a) obsolete packages from
     * a superseded master version are detectable without fragile inference
     * over plan_ids order, (b) the dependency gate can find "package k-1"
     * with one indexed query, and (c) per-card refresh needs no chain scan.
     * Alternative (JSON on the build) was rejected: it cannot be queried
     * per plan and duplicates the source of truth.
     *
     * Backfill: existing chain plans inherit (build, current version,
     * position in plan_ids). Standalone plans keep NULLs (gate bypasses).
     */
    public function up(): void
    {
        Schema::table('ai_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('master_build_id')->nullable()->after('conversation_id');
            $table->unsignedTinyInteger('master_version')->nullable()->after('master_build_id');
            $table->unsignedTinyInteger('package_index')->nullable()->after('master_version');
            $table->string('last_error', 500)->nullable()->after('executed_at');
            $table->index(['user_id', 'master_build_id', 'package_index'], 'ai_plans_build_pkg_idx');
        });

        foreach (\App\Models\AiStudyBuild::cursor() as $build) {
            $idx = 0;
            foreach (array_map('intval', (array) ($build->plan_ids ?? [])) as $pid) {
                $idx++;
                \App\Models\AiPlan::where('id', $pid)
                    ->where('user_id', $build->user_id)
                    ->whereNull('master_build_id')
                    ->update([
                        'master_build_id' => $build->id,
                        'master_version' => $build->version ?? 1,
                        'package_index' => $idx,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ai_plans', function (Blueprint $table) {
            $table->dropIndex('ai_plans_build_pkg_idx');
            $table->dropColumn(['master_build_id', 'master_version', 'package_index', 'last_error']);
        });
    }
};
