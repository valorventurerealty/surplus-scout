<?php

namespace Database\Factories;

use App\Enums\OutboundEmailStatus;
use App\Models\Contact;
use App\Models\OutboundEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OutboundEmail> */
class OutboundEmailFactory extends Factory
{
    protected $model = OutboundEmail::class;
    public function definition(): array
    {
        return ['token' => fake()->uuid(), 'user_id' => User::factory(), 'primary_contact_id' => Contact::factory(), 'status' => OutboundEmailStatus::Draft, 'from_address' => 'info@valorventure.us', 'from_name' => 'Valor Venture Realty', 'to_json' => [fake()->safeEmail()], 'cc_json' => [], 'bcc_json' => [], 'subject' => fake()->sentence(5), 'body_text' => fake()->paragraphs(2, true)];
    }
}
