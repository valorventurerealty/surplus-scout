<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()->first();

        Contact::query()->limit(8)->get()->each(function (Contact $contact) use ($user): void {
            Task::factory()->count(2)->for($contact, 'taskable')->create([
                'assigned_user_id' => $user?->id,
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);
        });
    }
}
