<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PropertyFinancialSplitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyFinancialSplit extends Model
{
    /** @use HasFactory<PropertyFinancialSplitFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'property_id', 'vvr_percentage', 'contact_one_id', 'contact_one_percentage',
        'contact_two_id', 'contact_two_percentage', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'vvr_percentage' => 'decimal:2',
            'contact_one_percentage' => 'decimal:2',
            'contact_two_percentage' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function contactOne(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_one_id');
    }

    public function contactTwo(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_two_id');
    }
}
