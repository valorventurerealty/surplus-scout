<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'taskable_type' => Contact::class,
            'taskable_id' => Contact::factory(),
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Pending,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'assigned_user_id' => User::factory(),
            'due_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'reminder_at' => null,
            'recurrence_frequency' => null,
            'recurrence_interval' => 1,
        ];
    }
}
