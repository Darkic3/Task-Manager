<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original note_share_note pivot was created without
     * created_at / updated_at while the relations use withTimestamps(),
     * causing SQLSTATE[42S22] on sync/attach.
     */
    public function up(): void
    {
        Schema::table('note_share_note', function (Blueprint $table) {
            if (! Schema::hasColumn('note_share_note', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('note_id');
            }
            if (! Schema::hasColumn('note_share_note', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('note_share_note', function (Blueprint $table) {
            if (Schema::hasColumn('note_share_note', 'created_at')
                && Schema::hasColumn('note_share_note', 'updated_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
