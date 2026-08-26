<?php

namespace Database\Factories;

use App\Models\ArmoryScriptStep;
use App\Models\ArmoryScriptStepOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArmoryScriptStepOption> */
class ArmoryScriptStepOptionFactory extends Factory
{
    public function definition(): array
    {
        return ['armory_script_step_id' => ArmoryScriptStep::factory(), 'label' => fake()->sentence(3), 'sequence' => 10];
    }
}
