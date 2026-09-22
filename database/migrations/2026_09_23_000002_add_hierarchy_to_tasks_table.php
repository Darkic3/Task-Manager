<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('project_id')
                ->constrained('tasks')->onDelete('cascade');
            $table->decimal('weight', 5, 2)->default(1.00)->after('status');
            $table->boolean('auto_weight')->default(true)->after('weight');
            $table->integer('sort_order')->default(0)->after('auto_weight');
            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['weight', 'auto_weight', 'sort_order']);
        });
    }
};
