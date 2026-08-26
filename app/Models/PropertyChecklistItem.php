<?php

namespace App\Models;

use App\Enums\PropertyChecklistKey;
use App\Models\Concerns\Auditable;
use Database\Factories\PropertyChecklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyChecklistItem extends Model
{
    /** @use HasFactory<PropertyChecklistItemFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'property_id', 'item_key', 'is_completed', 'evidence_url',
        'completed_at', 'completed_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'item_key' => PropertyChecklistKey::class,
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
