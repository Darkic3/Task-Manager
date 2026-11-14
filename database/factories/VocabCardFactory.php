<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use Illuminate\Database\Eloquent\Factories\Factory;

class VocabCardFactory extends Factory
{
    protected $model = VocabCard::class;

    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'user_id' => $user->id,
            'deck_id' => VocabDeck::factory()->create(['user_id' => $user->id])->id,
            'front' => $this->faker->word(),
            'back' => $this->faker->word(),
            'example' => null,
            'note' => null,
            'box' => 1,
            'next_review_at' => now()->toDateString(),
            'last_reviewed_at' => null,
            'review_count' => 0,
            'success_count' => 0,
            'is_mastered' => false,
        ];
    }
}
