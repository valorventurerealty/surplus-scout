<?php

namespace Database\Factories;

use App\Enums\PreAuctionAcquisitionStatus;
use App\Enums\PreAuctionEntitlementStatus;
use App\Models\Contact;
use App\Models\PreAuctionAcquisition;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PreAuctionAcquisition> */
class PreAuctionAcquisitionFactory extends Factory
{
    public function definition(): array
    {
        $purchase = fake()->randomFloat(2, 1000, 30000);
        $closing = fake()->randomFloat(2, 250, 2500);
        $projected = fake()->randomFloat(2, 5000, 100000);

        return [
            'token' => (string) Str::uuid(),
            'case_number' => 'PAQ-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'status' => PreAuctionAcquisitionStatus::Research,
            'owner_contact_id' => Contact::factory(), 'property_id' => Property::factory(),
            'assigned_user_id' => User::factory(), 'source' => 'County auction list',
            'state' => 'FL', 'county' => fake()->randomElement(['Orange', 'Osceola', 'Putnam', 'Polk']),
            'parcel_id' => fake()->numerify('##################'),
            'tax_deed_number' => fake()->bothify('###-20##'),
            'auction_at' => fake()->dateTimeBetween('+2 weeks', '+4 months'),
            'purchase_deadline' => fake()->dateTimeBetween('+1 week', '+2 weeks'),
            'purchase_price' => $purchase, 'closing_costs' => $closing, 'other_costs' => 0,
            'total_acquisition_cost' => $purchase + $closing,
            'projected_surplus' => $projected, 'projected_net' => $projected - $purchase - $closing,
            'entitlement_status' => PreAuctionEntitlementStatus::NotReviewed,
            'created_by' => User::factory(),
        ];
    }
}
