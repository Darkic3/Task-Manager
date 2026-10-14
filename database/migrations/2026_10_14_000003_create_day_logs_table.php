<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('day_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('kind', 20); // activity|journal
            $table->string('title', 255);
            $table->text('note')->nullable();
            $table->string('period', 30)->nullable(); // config('routines.periods') key
            $table->integer('duration_seconds')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('amount_unit', 30)->nullable(); // canonical: minute|hour|page|verse|count|km
            $table->string('start_time', 5)->nullable(); // HH:MM
            $table->string('end_time', 5)->nullable(); // HH:MM
            $table->string('logged_at', 5)->nullable(); // HH:MM
            $table->unsignedTinyInteger('mood')->nullable(); // 1-10, journals only
            $table->boolean('mood_picked')->default(false);
            $table->boolean('is_quit')->default(false); // flagged as something being quit
            $table->foreignId('time_entry_id')->nullable()->constrained('time_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_logs');
    }
};
