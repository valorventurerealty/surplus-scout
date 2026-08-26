<?php

namespace Database\Factories;

use App\Models\AiActionPlan;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiActionPlan> */
class AiActionPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'token' => fake()->uuid(),
            'conversation_id' => AiConversation::factory(),
            'user_id' => User::factory(),
            'intent' => 'general_question',
            'summary' => fake()->sentence(),
            'risk_level' => 0,
            'status' => 'needs_input',
        ];
    }
}
