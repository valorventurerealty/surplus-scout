<?php

namespace Database\Factories;

use App\Enums\NegotiationPlanStatus;
use App\Models\NegotiationPlan;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NegotiationPlan> */
class NegotiationPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->streetAddress().' Negotiation',
            'property_id' => Property::factory(),
            'status' => NegotiationPlanStatus::Active,
            'asking_price' => 22000,
            'all_in_amount' => 14100,
            'buyer_offer' => fake()->optional()->randomFloat(2, 11000, 22000),
            'counter_percent' => 77.5,
            'vvr_percentage' => 20,
            'investor_one_percentage' => 40,
            'investor_two_percentage' => 40,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
