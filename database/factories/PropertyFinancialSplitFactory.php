<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Property;
use App\Models\PropertyFinancialSplit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyFinancialSplit> */
class PropertyFinancialSplitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'vvr_percentage' => 20,
            'contact_one_id' => Contact::factory(),
            'contact_one_percentage' => 40,
            'contact_two_id' => Contact::factory(),
            'contact_two_percentage' => 40,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
