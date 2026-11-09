<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        $content = $this->faker->paragraphs(3, true);

        return [
            'user_id' => User::factory(),
            'book_id' => null,
            'title' => $this->faker->sentence(4),
            'content' => $content,
            'entry_date' => today()->toDateString(),
            'mood_score' => $this->faker->numberBetween(1, 10),
            'energy' => $this->faker->numberBetween(1, 5),
            'sleep_hours' => $this->faker->randomFloat(2, 4, 9),
            'sleep_quality' => $this->faker->numberBetween(1, 5),
            'weather_condition' => null,
            'weather_temp' => null,
            'location_name' => null,
            'location_lat' => null,
            'location_lng' => null,
            'word_count' => str_word_count($content),
            'is_pinned' => false,
            'is_favorite' => false,
            'status' => JournalEntry::STATUS_ACTIVE,
            'edited_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => JournalEntry::STATUS_DRAFT]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => JournalEntry::STATUS_ARCHIVED]);
    }
}
