<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 140);
            $table->string('color', 20)->default('#64748b');
            $table->string('icon', 60)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('label_note', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_label_id')->constrained('note_labels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['note_id', 'note_label_id']);
            $table->index('note_label_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_note');
        Schema::dropIfExists('note_labels');
    }
};