<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('content')->nullable();
            $table->string('kind', 30)->nullable();
            $table->foreignId('notebook_id')->nullable()->constrained('notebooks')->nullOnDelete();
            $table->string('change_source', 20)->default('user');
            $table->string('reason')->nullable();
            $table->json('changed_fields')->nullable();
            $table->timestamps();

            $table->index(['note_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_revisions');
    }
};