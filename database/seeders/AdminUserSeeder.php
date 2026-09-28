<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the admin user, or re-sync the existing one with the
     * credentials defined in the environment file.
     * php artisan db:seed --class=AdminUserSeeder
     */
    public function run(): void
    {
        $name = env('ADMIN_NAME', 'Ahora');
        $email = env('ADMIN_EMAIL', 'ahora@a.com');
        $password = env('ADMIN_PASSWORD', '123123');

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
            ])->save();

            $this->command?->info("Admin user [{$email}] already exists — credentials re-synced from .env.");

            return;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Admin user [{$email}] created from .env credentials.");
    }
}
