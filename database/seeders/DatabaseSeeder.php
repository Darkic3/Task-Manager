<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => env('ADMIN_NAME', 'Ahora'),
            'email' => env('ADMIN_EMAIL', 'ahora@a.com'),
            'password' => bcrypt(env('ADMIN_PASSWORD', '123123')),
        ]);

    }
}
