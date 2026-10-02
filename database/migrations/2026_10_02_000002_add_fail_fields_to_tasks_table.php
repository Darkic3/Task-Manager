<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Explicit "failed / won't do" marker with an optional note.
            // The task keeps its status/due_date; rescheduling just moves due_date.
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->text('fail_note')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['failed_at', 'fail_note']);
        });
    }
};
