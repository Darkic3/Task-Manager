<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('user_id')
                ->constrained('projects')->onDelete('cascade');
            $table->integer('sort_order')->default(0)->after('parent_id');
            $table->string('type')->default('project')->after('sort_order');
            $table->json('metadata')->nullable()->after('budget');
            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['sort_order', 'type', 'metadata']);
        });
    }
};
