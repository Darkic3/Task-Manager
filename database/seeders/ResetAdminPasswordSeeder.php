<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ResetAdminPasswordSeeder extends Seeder
{
    /**
     * Reset the existing admin user's password to 123 without touching
     * any other data.
     * php artisan db:seed --class=ResetAdminPasswordSeeder
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'ahora@a.com');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->command?->error("Admin user [{$email}] not found — nothing changed.");

            return;
        }

        $user->forceFill([
            'password' => Hash::make('123'),
        ])->save();

        $this->command?->info("Password for admin user [{$email}] reset to 123.");
    }
}
