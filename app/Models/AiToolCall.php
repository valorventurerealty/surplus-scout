<?php

namespace App\Models;

use Database\Factories\AiToolCallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolCall extends Model
{
    /** @use HasFactory<AiToolCallFactory> */
    use HasFactory;

    protected $fillable = [
        'action_plan_id', 'sequence', 'tool_name', 'action_summary', 'risk_level',
        'requires_approval', 'arguments_json', 'status', 'idempotency_key', 'result_json',
        'error_json', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'risk_level' => 'integer',
            'requires_approval' => 'boolean',
            'arguments_json' => 'array',
            'result_json' => 'array',
            'error_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function actionPlan(): BelongsTo
    {
        return $this->belongsTo(AiActionPlan::class);
    }
}
