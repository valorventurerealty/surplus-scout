<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        $nextFollowUpAt = fake()->optional()->dateTimeBetween('now', '+30 days');

        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mailing_address_line_1' => fake()->optional()->streetAddress(),
            'mailing_city' => fake()->optional()->city(),
            'mailing_state_province' => fake()->optional()->stateAbbr(),
            'mailing_postal_code' => fake()->optional()->postcode(),
            'mailing_country' => 'United States',
            'type' => fake()->randomElement(ContactType::cases()),
            'status' => fake()->randomElement(ContactStatus::cases()),
            'assigned_user_id' => User::factory(),
            'next_follow_up_at' => $nextFollowUpAt,
            'next_follow_up_purpose' => $nextFollowUpAt ? fake()->sentence(4) : null,
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
