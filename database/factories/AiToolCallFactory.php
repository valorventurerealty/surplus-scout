<?php

namespace Database\Factories;

use App\Models\AiActionPlan;
use App\Models\AiToolCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiToolCall> */
class AiToolCallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'action_plan_id' => AiActionPlan::factory(),
            'sequence' => 1,
            'tool_name' => 'get_properties',
            'action_summary' => 'Search properties',
            'risk_level' => 0,
            'requires_approval' => false,
            'arguments_json' => [],
            'status' => 'proposed',
            'idempotency_key' => hash('sha256', fake()->uuid()),
        ];
    }
}
