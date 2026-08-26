<?php

namespace App\Models;

use App\Enums\NegotiationPlanStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\NegotiationPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NegotiationPlan extends Model
{
    /** @use HasFactory<NegotiationPlanFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'property_id', 'sync_property_financials', 'buyer_contact_id', 'status', 'asking_price', 'all_in_amount', 'financials_synced_at',
        'buyer_offer', 'counter_percent', 'vvr_percentage', 'investor_one_percentage', 'investor_two_percentage',
        'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => NegotiationPlanStatus::class,
            'sync_property_financials' => 'boolean',
            'financials_synced_at' => 'datetime',
            'asking_price' => 'decimal:2',
            'all_in_amount' => 'decimal:2',
            'buyer_offer' => 'decimal:2',
            'counter_percent' => 'decimal:2',
            'vvr_percentage' => 'decimal:2',
            'investor_one_percentage' => 'decimal:2',
            'investor_two_percentage' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function buyerContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'buyer_contact_id');
    }
}
