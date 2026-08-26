<?php

namespace Database\Factories;

use App\Enums\PhoneInteractionDirection;
use App\Enums\PhoneInteractionMatchStatus;
use App\Enums\PhoneInteractionType;
use App\Models\PhoneInteraction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PhoneInteraction> */
class PhoneInteractionFactory extends Factory
{
    protected $model = PhoneInteraction::class;

    public function definition(): array
    {
        return [
            'token' => (string) Str::uuid(),
            'provider' => 'beside',
            'provider_event_id' => (string) Str::uuid(),
            'event_type' => PhoneInteractionType::Call,
            'direction' => PhoneInteractionDirection::Inbound,
            'match_status' => PhoneInteractionMatchStatus::Unmatched,
            'caller_phone' => fake()->phoneNumber(),
            'occurred_at' => now(),
            'summary' => fake()->sentence(),
            'received_at' => now(),
        ];
    }
}
