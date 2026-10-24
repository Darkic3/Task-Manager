<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3 provider-call metrics. One row per provider round-trip that the
     * server initiates for a user-facing operation.
     *
     * Why a table (not just ai.log): the P3 contract requires MEASURABLE
     * per-operation metrics (tokens in/out, latency, attempts, model,
     * outcome) that tests and ops can query — log lines are not queryable
     * and rotate away. The table carries NO payload content, NO keys, NO
     * prompts: only counts, timings, model names and error codes, so it
     * can never leak secrets (enforced by AiProviderCallLog::record()).
     */
    public function up(): void
    {
        Schema::create('ai_provider_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('operation', 40)->index();
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->string('outcome', 30)->index();
            $table->string('error_code', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_calls');
    }
};
