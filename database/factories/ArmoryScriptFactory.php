<?php

namespace Database\Factories;

use App\Enums\ArmoryScriptCategory;
use App\Enums\ArmoryScriptStatus;
use App\Models\ArmoryScript;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ArmoryScript> */
class ArmoryScriptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token' => (string) Str::uuid(),
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(ArmoryScriptCategory::cases()),
            'status' => ArmoryScriptStatus::Active,
            'version_label' => '1.0',
            'description' => fake()->optional()->sentence(),
            'content_text' => fake()->paragraphs(3, true),
            'uploaded_by' => User::factory(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
