<?php

namespace Database\Factories;

use App\Models\JournalMood;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalMoodFactory extends Factory
{
    protected $model = JournalMood::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'color' => '#7c3aed',
            'icon' => 'bi-emoji-smile',
            'category' => JournalMood::CATEGORY_NEUTRAL,
            'sort_order' => 0,
        ];
    }
}
