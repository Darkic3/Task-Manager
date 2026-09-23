<?php

namespace Database\Factories;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoutineFactory extends Factory
{
    protected $model = Routine::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => null,
            'frequency' => 'daily',
            'time_period' => null,
            'days' => null,
            'weeks' => null,
            'months' => null,
            'month_days' => null,
            'start_time' => null,
            'end_time' => null,
        ];
    }
}
