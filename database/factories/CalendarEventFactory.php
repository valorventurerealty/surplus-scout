<?php

namespace Database\Factories;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\AuctionCounty;
use App\Enums\AuctionEventType;
use App\Enums\CalendarEventSource;
use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CalendarEvent> */
class CalendarEventFactory extends Factory
{
    public function definition(): array
    {
        $parcel = fake()->unique()->numerify('##-##-##-####-####-####');

        return [
            'title' => null,
            'parcel_number' => $parcel,
            'normalized_parcel_number' => app(PropertyNormalizer::class)->parcelId($parcel),
            'event_type' => fake()->randomElement(AuctionEventType::cases()),
            'source' => CalendarEventSource::Vvr,
            'starts_at' => fake()->dateTimeBetween('now', '+90 days'),
            'auction_url' => 'https://example.test/auction/'.fake()->uuid(),
            'property_address' => fake()->streetAddress().', '.fake()->city().', FL',
            'county' => fake()->randomElement(AuctionCounty::cases()),
            'max_bid' => fake()->randomFloat(2, 5000, 150000),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
