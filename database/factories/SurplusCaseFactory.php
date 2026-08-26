<?php

namespace Database\Factories;

use App\Enums\SurplusCaseStatus;
use App\Models\Contact;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SurplusCase> */
class SurplusCaseFactory extends Factory
{
    public function definition(): array
    {
        $surplus = fake()->randomFloat(2, 5000, 125000);
        $percentage = 12;

        return [
            'token' => (string) Str::uuid(),
            'case_number' => 'SUR-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'status' => fake()->randomElement(SurplusCaseStatus::cases()),
            'claimant_contact_id' => Contact::factory(),
            'property_id' => Property::factory(),
            'assigned_user_id' => User::factory(),
            'source' => fake()->randomElement(['County list', 'Referral', 'Direct outreach']),
            'state' => 'FL', 'county' => fake()->randomElement(['Putnam', 'Osceola', 'Polk', 'Marion']),
            'parcel_id' => fake()->bothify('##-##-##-####-####-####'),
            'tax_deed_number' => fake()->optional()->bothify('###-20##'),
            'foreclosure_case_number' => fake()->unique()->bothify('20##-CA-######'),
            'certificate_number' => fake()->optional()->numerify('########'),
            'surplus_amount' => $surplus, 'agreed_fee_percentage' => $percentage,
            'expected_fee' => round($surplus * $percentage / 100, 2),
            'sale_date' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'claim_deadline' => fake()->dateTimeBetween('+1 month', '+1 year'),
            'notes' => fake()->optional()->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
