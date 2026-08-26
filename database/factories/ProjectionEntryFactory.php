<?php

namespace Database\Factories;

use App\Enums\ProjectionCategory;
use App\Models\ProjectionEntry;
use App\Models\ProjectionScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectionEntry> */
class ProjectionEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'projection_scenario_id' => ProjectionScenario::factory(),
            'category' => fake()->randomElement(ProjectionCategory::cases()),
            'year' => fake()->numberBetween(2026, 2030),
            'month' => fake()->numberBetween(1, 12),
            'projected_units' => fake()->numberBetween(0, 20),
        ];
    }
}
