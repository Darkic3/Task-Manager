<?php

namespace Database\Factories;

use App\Models\JournalBook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalBookFactory extends Factory
{
    protected $model = JournalBook::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'color' => '#7c3aed',
            'icon' => 'bi-journal',
            'description' => null,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
