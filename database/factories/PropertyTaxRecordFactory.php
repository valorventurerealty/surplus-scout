<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyTaxRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyTaxRecord> */
class PropertyTaxRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(), 'tax_year' => now()->subYear()->year,
            'market_value' => fake()->randomFloat(2, 1000, 250000),
            'assessed_value' => fake()->randomFloat(2, 1000, 250000),
            'taxable_value' => fake()->randomFloat(2, 1000, 250000),
            'prior_year_final_tax' => fake()->randomFloat(2, 10, 5000),
            'source_document_type' => 'prior_year_tax_notice', 'created_by' => User::factory(),
        ];
    }
}
