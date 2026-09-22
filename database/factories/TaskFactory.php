<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'parent_id' => null,
            'title' => $this->faker->sentence(4),
            'description' => null,
            'due_date' => null,
            'priority' => 'medium',
            'status' => 'to_do',
            'weight' => 1,
            'auto_weight' => true,
            'sort_order' => 0,
        ];
    }
}
