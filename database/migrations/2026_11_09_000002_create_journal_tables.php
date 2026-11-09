<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('color', 20)->nullable();
            $table->string('icon', 60)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('journal_moods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 20)->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('category', 20)->default('neutral'); // positive|negative|neutral
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });

        Schema::create('journal_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 20)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained('journal_books')->nullOnDelete();
            $table->string('title', 255)->nullable();
            $table->longText('content');
            $table->date('entry_date');
            $table->unsignedTinyInteger('mood_score')->nullable(); // 1-10
            $table->unsignedTinyInteger('energy')->nullable(); // 1-5
            $table->decimal('sleep_hours', 4, 2)->nullable();
            $table->unsignedTinyInteger('sleep_quality')->nullable(); // 1-5
            $table->string('weather_condition', 60)->nullable();
            $table->decimal('weather_temp', 5, 2)->nullable();
            $table->string('location_name', 191)->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->string('status', 20)->default('active'); // draft|active|archived
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'entry_date']);
            $table->index(['user_id', 'deleted_at']);
            $table->index(['book_id']);
            $table->index(['status']);
        });

        Schema::create('journal_entry_tag', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('journal_tag_id')->constrained('journal_tags')->cascadeOnDelete();
            $table->primary(['journal_entry_id', 'journal_tag_id']);
        });

        Schema::create('journal_entry_mood', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('journal_mood_id')->constrained('journal_moods')->cascadeOnDelete();
            $table->unsignedTinyInteger('intensity')->default(3); // 1-5
            $table->string('note', 500)->nullable();
            $table->primary(['journal_entry_id', 'journal_mood_id']);
        });

        Schema::create('journal_entry_link', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->string('linkable_type', 191);
            $table->unsignedBigInteger('linkable_id');
            $table->string('label', 191)->nullable();
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id']);
        });

        Schema::create('journal_entry_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->string('title', 255)->nullable();
            $table->longText('content');
            $table->unsignedTinyInteger('mood_score')->nullable();
            $table->json('tags_snapshot')->nullable();
            $table->timestamp('edited_at');
            $table->timestamps();

            $table->index(['journal_entry_id', 'edited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_revisions');
        Schema::dropIfExists('journal_entry_link');
        Schema::dropIfExists('journal_entry_mood');
        Schema::dropIfExists('journal_entry_tag');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journal_tags');
        Schema::dropIfExists('journal_moods');
        Schema::dropIfExists('journal_books');
    }
};
