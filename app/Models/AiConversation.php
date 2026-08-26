<?php

namespace App\Models;

use Database\Factories\AiConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    /** @use HasFactory<AiConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'token', 'user_id', 'title', 'intent', 'status', 'result_json', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    public function propertyIntakes(): HasMany
    {
        return $this->hasMany(PropertyIntakeFile::class, 'ai_conversation_id');
    }

    public function surplusIntakes(): HasMany
    {
        return $this->hasMany(SurplusIntakeFile::class, 'ai_conversation_id');
    }

    public function surplusCsvImports(): HasMany
    {
        return $this->hasMany(AiSurplusCsvImport::class, 'ai_conversation_id');
    }

    public function preAuctionCsvImports(): HasMany
    {
        return $this->hasMany(AiPreAuctionCsvImport::class, 'ai_conversation_id');
    }

    public function actionPlans(): HasMany
    {
        return $this->hasMany(AiActionPlan::class, 'conversation_id');
    }
}
