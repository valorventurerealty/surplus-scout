<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaskTemplate> */
class TaskTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => TaskPriority::Normal,
            'due_in_days' => 3,
            'recurrence_interval' => 1,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
