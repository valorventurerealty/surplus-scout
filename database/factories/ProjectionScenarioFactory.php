<?php

namespace Database\Factories;

use App\Enums\ProjectionScenarioStatus;
use App\Models\ProjectionScenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProjectionScenario> */
class ProjectionScenarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token' => (string) Str::uuid(),
            'name' => fake()->unique()->words(3, true).' projections',
            'status' => ProjectionScenarioStatus::Draft,
            'start_year' => 2026,
            'end_year' => 2030,
            'is_default' => false,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
