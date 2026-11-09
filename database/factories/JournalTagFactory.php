<?php

namespace Database\Factories;

use App\Models\JournalTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalTagFactory extends Factory
{
    protected $model = JournalTag::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'color' => '#7c3aed',
        ];
    }
}
