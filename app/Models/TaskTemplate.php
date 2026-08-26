<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskRecurrence;
use App\Models\Concerns\Auditable;
use Database\Factories\TaskTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskTemplate extends Model
{
    /** @use HasFactory<TaskTemplateFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name', 'title', 'description', 'priority', 'due_in_days', 'reminder_lead_minutes',
        'recurrence_frequency', 'recurrence_interval', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'recurrence_frequency' => TaskRecurrence::class,
            'due_in_days' => 'integer',
            'reminder_lead_minutes' => 'integer',
            'recurrence_interval' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
