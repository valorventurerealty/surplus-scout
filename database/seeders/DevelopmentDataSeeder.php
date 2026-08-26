<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Contact::factory()->count(18)->create(['assigned_user_id' => $owner->id, 'created_by' => $owner->id]);
    }
}
