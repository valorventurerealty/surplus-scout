<?php

namespace App\Models;

use Database\Factories\AiActionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiActionPlan extends Model
{
    /** @use HasFactory<AiActionPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'token', 'conversation_id', 'user_id', 'intent', 'summary', 'risk_level', 'status',
        'missing_information_json', 'warnings_json', 'result_json', 'expires_at',
        'approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_level' => 'integer',
            'missing_information_json' => 'array',
            'warnings_json' => 'array',
            'result_json' => 'array',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class, 'action_plan_id')->orderBy('sequence');
    }
}
