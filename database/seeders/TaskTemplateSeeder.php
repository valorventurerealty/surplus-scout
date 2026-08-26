<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $actorId = User::query()->whereIn('role', ['owner', 'admin'])->value('id');
        $titles = [
            'Verify ownership',
            'Verify parcel ID',
            'Review title',
            'Verify taxes',
            'Review flood zone',
            'Review wetlands',
            'Verify legal access',
            'Verify utilities',
            'Review zoning',
            'Review buildability',
            'Upload photos',
            'Review comparable sales',
            'Confirm closing documents',
        ];

        foreach ($titles as $title) {
            TaskTemplate::query()->updateOrCreate(['name' => $title], [
                'title' => $title,
                'description' => 'Complete this due-diligence check and record the verified result in the associated CRM record.',
                'priority' => TaskPriority::Normal->value,
                'due_in_days' => 7,
                'reminder_lead_minutes' => 1440,
                'recurrence_frequency' => null,
                'recurrence_interval' => 1,
                'is_active' => true,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }
    }
}
