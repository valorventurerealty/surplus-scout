<?php

namespace Database\Factories;

use App\Enums\ProjectionCategory;
use App\Models\ProjectionAssumption;
use App\Models\ProjectionScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectionAssumption> */
class ProjectionAssumptionFactory extends Factory
{
    public function definition(): array
    {
        $category = fake()->randomElement(ProjectionCategory::cases());

        return [
            'projection_scenario_id' => ProjectionScenario::factory(),
            'category' => $category,
            'average_net_profit' => $category->defaultAverageNetProfit(),
        ];
    }
}
