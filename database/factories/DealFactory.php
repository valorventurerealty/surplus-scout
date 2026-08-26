<?php

namespace Database\Factories;

use App\Enums\DealStatus;
use App\Enums\DealType;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Deal> */
class DealFactory extends Factory
{
    public function definition(): array
    {
        $contractDate = fake()->dateTimeBetween('-45 days', '+20 days');

        return [
            'token' => (string) Str::uuid(),
            'deal_number' => 'VVR-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'title' => fake()->streetAddress().' '.fake()->randomElement(['Acquisition', 'Disposition', 'Transaction']),
            'type' => fake()->randomElement(DealType::cases()),
            'status' => fake()->randomElement(DealStatus::cases()),
            'property_id' => Property::factory(),
            'primary_contact_id' => Contact::factory(),
            'assigned_user_id' => User::factory(),
            'source' => fake()->randomElement(['Direct mail', 'Referral', 'County list', 'Inbound']),
            'contract_date' => $contractDate,
            'due_diligence_deadline' => (clone $contractDate)->modify('+14 days'),
            'projected_close_date' => (clone $contractDate)->modify('+30 days'),
            'actual_close_date' => null,
            'offer_amount' => fake()->randomFloat(2, 5000, 150000),
            'contract_amount' => fake()->randomFloat(2, 5000, 150000),
            'earnest_money' => fake()->randomFloat(2, 100, 5000),
            'projected_revenue' => fake()->randomFloat(2, 1000, 50000),
            'actual_revenue' => null,
            'document_drive_url' => null,
            'notes' => fake()->optional()->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
