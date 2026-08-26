<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoogleCalendarConnection> */
class GoogleCalendarConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Owner]),
            'google_account_email' => fake()->safeEmail(),
            'calendar_id' => 'primary',
            'calendar_name' => 'VVR Command Center',
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'scopes' => config('services.google_calendar.scopes'),
            'is_active' => true,
            'inbound_sync_enabled' => false,
            'connected_at' => now(),
        ];
    }
}
