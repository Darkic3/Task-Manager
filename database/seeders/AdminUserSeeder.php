<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Create/promote the configured administrator without seeding demo data.
     * Production requires an explicitly configured strong ADMIN_PASSWORD.
     */
    public function run(): void
    {
        $name = (string) config('admin.name');
        $username = Str::lower(trim((string) config('admin.username')));
        $email = Str::lower(trim((string) config('admin.email')));
        $password = (string) config('admin.password', '');

        if ($name === '' || ! preg_match('/^[a-z0-9_-]{3,30}$/', $username) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('ADMIN_NAME, ADMIN_USERNAME, and ADMIN_EMAIL must be valid before seeding the administrator.');
        }

        $user = User::query()->where('email', $email)->first();
        $usernameOwner = User::query()->where('username', $username)
            ->when($user, fn ($query) => $query->where('id', '!=', $user->id))
            ->first();

        if ($usernameOwner) {
            throw new \RuntimeException("ADMIN_USERNAME [{$username}] is already used by a different account.");
        }

        if ($password === '' && ! $user && app()->environment('production')) {
            throw new \RuntimeException('Set a unique ADMIN_PASSWORD of at least 14 characters before creating the production administrator.');
        }

        if ($password !== '' && mb_strlen($password) < 2) {
            throw new \RuntimeException('ADMIN_PASSWORD must contain at least 2 characters.');
        }

        $generatedPassword = false;
        if ($password === '' && ! $user) {
            $password = Str::random(48);
            $generatedPassword = true;
        }

        $user ??= new User;
        $user->forceFill([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_admin' => true,
        ]);

        if ($password !== '') {
            $user->password = Hash::make($password);
        }

        $user->save();

        $this->command?->info("Administrator account [{$email}] is ready.");
        if ($generatedPassword) {
            $this->command?->warn("One-time local password: {$password}");
        }
    }
}
