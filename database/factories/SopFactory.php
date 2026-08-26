<?php

namespace Database\Factories;

use App\Enums\SopDepartment;
use App\Enums\SopStatus;
use App\Models\Sop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Sop> */
class SopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token' => (string) Str::uuid(),
            'title' => fake()->randomElement(['Property Research', 'Seller Intake', 'Due Diligence', 'Closing File Review']).' Procedure',
            'department' => fake()->randomElement(SopDepartment::cases()),
            'status' => fake()->randomElement(SopStatus::cases()),
            'version_label' => fake()->randomElement(['1.0', '1.1', '2.0']),
            'owner_user_id' => User::factory(),
            'summary' => fake()->paragraph(),
            'content_text' => "Purpose\n".fake()->sentence()."\n\nProcedure\n1. ".fake()->sentence()."\n2. ".fake()->sentence(),
            'effective_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'review_date' => fake()->dateTimeBetween('now', '+1 year'),
            'created_by' => User::factory(),
        ];
    }
}
