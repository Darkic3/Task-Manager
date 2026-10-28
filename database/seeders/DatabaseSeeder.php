<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeders are disabled outside local and testing environments.');
        }

        $this->call(AdminUserSeeder::class);

        $this->call(DemoStudyDataSeeder::class);
    }
}
