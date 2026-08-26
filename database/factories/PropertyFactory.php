<?php

namespace Database\Factories;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\WetlandsStatus;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $parcel = fake()->unique()->numerify('##-###-###');
        $county = fake()->citySuffix().' County';
        $address = fake()->streetAddress();
        $city = fake()->city();
        $state = fake()->stateAbbr();
        $zip = fake()->postcode();
        $normalizer = app(PropertyNormalizer::class);
        $purchasePrice = fake()->randomFloat(2, 5000, 250000);
        $taxes = fake()->randomFloat(2, 0, 15000);
        $attorneyFees = fake()->randomFloat(2, 0, 5000);
        $realtorFees = fake()->randomFloat(2, 0, 15000);
        $otherCosts = fake()->randomFloat(2, 0, 2500);
        $expectedSalesPrice = fake()->randomFloat(2, $purchasePrice, $purchasePrice * 1.8);
        $allInAmount = $purchasePrice + $taxes + $attorneyFees + $realtorFees + $otherCosts;

        return [
            'parcel_id' => $parcel,
            'normalized_parcel_id' => $normalizer->parcelId($parcel),
            'county' => $county,
            'normalized_county' => $normalizer->county($county),
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'postal_code' => $zip,
            'normalized_address' => $normalizer->address($address, $city, $state, $zip),
            'property_type' => fake()->randomElement(PropertyType::cases()),
            'status' => fake()->randomElement(PropertyStatus::cases()),
            'acreage' => fake()->randomFloat(4, 0.1, 100),
            'wetlands_status' => WetlandsStatus::Unknown,
            'utilities' => ['electricity' => 'Needs research', 'water' => 'Needs research'],
            'document_drive_url' => null,
            'closing_documents_url' => null,
            'owner_contact_id' => Contact::factory(),
            'purchase_price' => $purchasePrice,
            'taxes' => $taxes,
            'attorney_fees' => $attorneyFees,
            'realtor_fees' => $realtorFees,
            'other_costs' => $otherCosts,
            'all_in_amount' => $allInAmount,
            'expected_sales_price' => $expectedSalesPrice,
            'expected_profit' => $expectedSalesPrice - $allInAmount,
            'created_by' => User::factory(),
        ];
    }
}
