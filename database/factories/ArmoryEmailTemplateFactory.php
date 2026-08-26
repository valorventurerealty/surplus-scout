<?php

namespace Database\Factories;

use App\Enums\ArmoryEmailTemplateCategory;
use App\Enums\ArmoryEmailTemplateStatus;
use App\Models\ArmoryEmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ArmoryEmailTemplate> */
class ArmoryEmailTemplateFactory extends Factory
{
    protected $model = ArmoryEmailTemplate::class;

    public function definition(): array
    {
        return [
            'token' => (string) Str::uuid(),
            'name' => fake()->sentence(4),
            'category' => fake()->randomElement(ArmoryEmailTemplateCategory::cases()),
            'status' => ArmoryEmailTemplateStatus::Active,
            'version_label' => '1.0',
            'description' => fake()->optional()->sentence(),
            'subject' => 'Regarding {{property_address}}',
            'body_text' => "Hello {{first_name}},\n\n".fake()->paragraph(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
