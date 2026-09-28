<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow tasks without a parent project: project_id becomes nullable and
     * the foreign key switches from cascade delete to set-null.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Backfill nulls with the user's first project so the column can be
        // made NOT NULL again.
        DB::statement('
            UPDATE tasks t
            JOIN (SELECT user_id, MIN(id) AS first_project_id FROM projects GROUP BY user_id) p
                ON p.user_id = t.user_id
            SET t.project_id = p.first_project_id
            WHERE t.project_id IS NULL
        ');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
