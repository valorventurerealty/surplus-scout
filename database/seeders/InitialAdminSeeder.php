<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('INITIAL_ADMIN_EMAIL');
        $password = env('INITIAL_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            if (app()->environment('production')) {
                throw new RuntimeException('INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD are required for production seeding.');
            }

            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('INITIAL_ADMIN_NAME', 'VVR Owner'),
                'password' => Hash::make($password),
                'role' => UserRole::Owner,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
