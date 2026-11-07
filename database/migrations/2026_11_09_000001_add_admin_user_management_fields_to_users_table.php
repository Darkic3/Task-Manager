<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Administrator user management: accounts can be suspended (is_active)
     * and soft-deleted (deleted_at). Nothing is ever hard-deleted, so every
     * record the user owns stays intact for a later restore.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_admin');
            $table->timestamp('deleted_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'deleted_at']);
        });
    }
};
