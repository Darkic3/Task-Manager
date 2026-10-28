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
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('AdminUserSeeder is only available in local and testing environments.');
        }

        $name = env('ADMIN_NAME', 'Ahora');
        $email = env('ADMIN_EMAIL', 'ahora@a.com');
        $password = env('ADMIN_PASSWORD', '123123');
        $username = env('ADMIN_USERNAME', 'admin');

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'name' => $name,
                'username' => $username,
                'is_admin' => true,
                'password' => Hash::make($password),
            ])->save();

            $this->command?->info("Admin user [{$email}] already exists — credentials re-synced from .env.");

            return;
        }

        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);
        $user->forceFill(['is_admin' => true])->save();

        $this->command?->info("Admin user [{$email}] created from .env credentials.");
    }
}
