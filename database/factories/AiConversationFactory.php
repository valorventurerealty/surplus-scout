<?php

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiConversation> */
class AiConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token' => fake()->uuid(),
            'user_id' => User::factory(),
            'title' => 'I bought this fictional property',
            'intent' => 'create_property_from_documents',
            'status' => 'awaiting_approval',
            'last_message_at' => now(),
        ];
    }
}
