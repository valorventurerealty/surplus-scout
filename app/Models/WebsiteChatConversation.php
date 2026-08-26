<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteChatConversation extends Model
{
    protected $fillable = [
        'token', 'session_id', 'contact_id', 'task_id', 'topic', 'status',
        'visitor_name', 'visitor_email', 'visitor_phone', 'property_address',
        'parcel_id', 'message', 'transcript', 'page_url', 'consent_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'transcript' => 'array',
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function getTopicLabelAttribute(): string
    {
        return match ($this->topic) {
            'seller' => 'Sell a property',
            'tax_auction' => 'Property facing tax auction',
            'surplus' => 'Surplus funds',
            default => 'Something else',
        };
    }
}
