<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Seeder;

class CalendarEventSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()->first();
        CalendarEvent::factory()->count(8)->create([
            'created_by' => $user?->id,
            'updated_by' => $user?->id,
        ]);
    }
}
