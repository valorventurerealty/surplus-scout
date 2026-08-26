<?php

namespace Database\Factories;

use App\Enums\ArmorySessionStatus;
use App\Models\ArmoryScript;
use App\Models\ArmorySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ArmorySession> */
class ArmorySessionFactory extends Factory
{
    public function definition(): array
    {
        return ['token' => (string) Str::uuid(), 'armory_script_id' => ArmoryScript::factory(), 'user_id' => User::factory(), 'status' => ArmorySessionStatus::InProgress, 'started_at' => now()];
    }
}
