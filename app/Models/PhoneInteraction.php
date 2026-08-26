<?php

namespace App\Models;

use App\Enums\PhoneInteractionDirection;
use App\Enums\PhoneInteractionMatchStatus;
use App\Enums\PhoneInteractionType;
use Database\Factories\PhoneInteractionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneInteraction extends Model
{
    /** @use HasFactory<PhoneInteractionFactory> */
    use HasFactory;

    protected $fillable = [
        'token', 'provider', 'provider_event_id', 'event_type', 'direction', 'contact_id',
        'match_status', 'caller_phone', 'normalized_phone', 'caller_name', 'caller_email',
        'caller_company', 'inbox', 'occurred_at', 'duration_seconds', 'summary', 'transcript',
        'recording_url', 'action_items', 'provider_payload', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => PhoneInteractionType::class,
            'direction' => PhoneInteractionDirection::class,
            'match_status' => PhoneInteractionMatchStatus::class,
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'duration_seconds' => 'integer',
            'action_items' => 'array',
            'provider_payload' => 'array',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->contact?->full_name ?: ($this->caller_name ?: ($this->caller_phone ?: 'Unknown caller'));
    }
}
