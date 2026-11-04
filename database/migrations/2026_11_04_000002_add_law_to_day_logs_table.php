<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('day_logs', function (Blueprint $table) {
            $table->foreignId('law_id')->nullable()->after('time_entry_id')
                ->constrained()->nullOnDelete();
            $table->tinyInteger('law_direction')->nullable()->after('law_id'); // +1 for | -1 against
        });
    }

    public function down(): void
    {
        Schema::table('day_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('law_id');
            $table->dropColumn('law_direction');
        });
    }
};
