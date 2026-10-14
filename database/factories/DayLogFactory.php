<?php

namespace Database\Factories;

use App\Models\DayLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DayLogFactory extends Factory
{
    protected $model = DayLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => today()->toDateString(),
            'kind' => DayLog::KIND_ACTIVITY,
            'title' => $this->faker->sentence(3),
            'note' => null,
            'period' => null,
            'duration_seconds' => null,
            'amount' => null,
            'amount_unit' => null,
            'start_time' => null,
            'end_time' => null,
            'logged_at' => '20:00',
            'mood' => null,
            'mood_picked' => false,
            'is_quit' => false,
            'time_entry_id' => null,
        ];
    }

    public function journal(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => DayLog::KIND_JOURNAL,
            'mood' => 7,
            'mood_picked' => false,
        ]);
    }
}
