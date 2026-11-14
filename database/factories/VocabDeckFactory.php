<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VocabDeck;
use Illuminate\Database\Eloquent\Factories\Factory;

class VocabDeckFactory extends Factory
{
    protected $model = VocabDeck::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'description' => null,
            'color' => '#6366f1',
            'sort_order' => 0,
        ];
    }
}
