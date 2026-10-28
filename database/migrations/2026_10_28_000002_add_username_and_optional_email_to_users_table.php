<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
        });

        DB::table('users')->select(['id', 'name', 'email'])->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $source = filled($user->email)
                    ? Str::before($user->email, '@')
                    : $user->name;
                $base = Str::slug($source, '_') ?: 'user';
                $suffix = '_'.(string) $user->id;
                $username = substr($base, 0, 30 - strlen($suffix)).$suffix;

                DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Cannot roll back optional emails while users without an email exist.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
