<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('icon', 60)->default('bi-shield-check');
            $table->string('color', 7)->default('#7c3aed');
            $table->unsignedTinyInteger('coefficient')->default(1); // 1..10 — XP weight
            $table->date('starts_on');
            $table->date('ends_on')->nullable(); // null = open-ended (ongoing)
            $table->string('status', 20)->default('active'); // active|paused|archived
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'starts_on', 'ends_on']);
        });

        Schema::create('law_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('law_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type', 120); // Task|Routine|Project class names
            $table->unsignedBigInteger('linkable_id');
            $table->tinyInteger('effect')->default(1); // +1 for-direction | -1 against-direction
            $table->boolean('is_excluded')->default(false); // opt-out of project inheritance
            $table->timestamps();

            $table->unique(['law_id', 'linkable_type', 'linkable_id'], 'law_links_unique_target');
            $table->index(['linkable_type', 'linkable_id']);
        });

        Schema::create('law_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('law_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->tinyInteger('direction'); // +1 for | -1 against
            $table->integer('xp_delta'); // direction * law.coefficient at log time
            $table->string('source', 20)->default('manual'); // manual|auto
            $table->string('linkable_type', 120)->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_on']);
            $table->index(['law_id', 'occurred_on']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('law_events');
        Schema::dropIfExists('law_links');
        Schema::dropIfExists('laws');
    }
};
