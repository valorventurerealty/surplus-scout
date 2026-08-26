<?php

namespace Database\Factories;

use App\Enums\PropertyChecklistKey;
use App\Models\Property;
use App\Models\PropertyChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyChecklistItem> */
class PropertyChecklistItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'item_key' => fake()->randomElement(PropertyChecklistKey::cases()),
            'is_completed' => false,
            'updated_by' => User::factory(),
        ];
    }
}
