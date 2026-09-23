<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen the routines.frequency ENUM with the new `every_n_days` mode.
 * Fresh databases get it from the create migration; this upgrades existing ones.
 * SQLite enforces the enum via a CHECK constraint baked at create-time and has
 * no MODIFY syntax — there the constraint is recreated by the create migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE routines MODIFY COLUMN frequency ENUM('daily','weekly','monthly','every_n_days') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE routines SET frequency = 'daily' WHERE frequency = 'every_n_days'");
            DB::statement("ALTER TABLE routines MODIFY COLUMN frequency ENUM('daily','weekly','monthly') NOT NULL");
        }
    }
};
