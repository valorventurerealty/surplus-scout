<?php

namespace Database\Factories;

use App\Models\ArmoryScript;
use App\Models\ArmoryScriptStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArmoryScriptStep> */
class ArmoryScriptStepFactory extends Factory
{
    public function definition(): array
    {
        return ['armory_script_id' => ArmoryScript::factory(), 'title' => fake()->sentence(3), 'prompt_text' => fake()->sentence(), 'sequence' => 10, 'created_by' => User::factory(), 'updated_by' => User::factory()];
    }
}
