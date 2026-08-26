<?php

namespace App\Models;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\PreAuctionAcquisitionStatus;
use App\Enums\PreAuctionEntitlementStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\PreAuctionAcquisitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreAuctionAcquisition extends Model
{
    /** @use HasFactory<PreAuctionAcquisitionFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'token', 'case_number', 'status', 'owner_contact_id', 'property_id', 'assigned_user_id',
        'source', 'state', 'county', 'parcel_id', 'normalized_parcel_id', 'tax_deed_number',
        'certificate_number', 'assessor_market_value', 'appraiser_url', 'property_details_url',
        'auction_at', 'auction_url', 'purchase_deadline', 'contract_date',
        'closing_date', 'deed_recorded_date', 'recording_instrument_number',
        'non_redemption_reviewed_at', 'non_redemption_reviewed_by', 'purchase_price',
        'closing_costs', 'other_costs', 'total_acquisition_cost', 'projected_surplus',
        'projected_net', 'auction_winning_bid', 'surplus_generated', 'entitlement_status',
        'entitlement_reviewed_at', 'entitlement_reviewed_by', 'entitlement_notes',
        'claim_submitted_at', 'paid_at', 'amount_recovered', 'actual_net',
        'document_drive_url', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PreAuctionAcquisitionStatus::class,
            'entitlement_status' => PreAuctionEntitlementStatus::class,
            'auction_at' => 'datetime',
            'purchase_deadline' => 'date', 'contract_date' => 'date', 'closing_date' => 'date',
            'deed_recorded_date' => 'date', 'non_redemption_reviewed_at' => 'date',
            'entitlement_reviewed_at' => 'date', 'claim_submitted_at' => 'date', 'paid_at' => 'date',
            'assessor_market_value' => 'decimal:2',
            'purchase_price' => 'decimal:2', 'closing_costs' => 'decimal:2', 'other_costs' => 'decimal:2',
            'total_acquisition_cost' => 'decimal:2', 'projected_surplus' => 'decimal:2',
            'projected_net' => 'decimal:2', 'auction_winning_bid' => 'decimal:2',
            'surplus_generated' => 'decimal:2', 'amount_recovered' => 'decimal:2', 'actual_net' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PreAuctionAcquisition $case): void {
            $case->normalized_parcel_id = app(PropertyNormalizer::class)->parcelId($case->parcel_id);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function ownerContact(): BelongsTo { return $this->belongsTo(Contact::class, 'owner_contact_id'); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function assignedUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function nonRedemptionReviewer(): BelongsTo { return $this->belongsTo(User::class, 'non_redemption_reviewed_by'); }
    public function entitlementReviewer(): BelongsTo { return $this->belongsTo(User::class, 'entitlement_reviewed_by'); }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->withPivot(['id', 'role', 'relationship_notes', 'created_by'])
            ->withTimestamps();
    }

    public function tasks(): MorphMany { return $this->morphMany(Task::class, 'taskable'); }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [PreAuctionAcquisitionStatus::Closed, PreAuctionAcquisitionStatus::Disqualified]);
    }

    public function scopeOrderByPipelineStatus(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $statuses = PreAuctionAcquisitionStatus::orderedValues();
        $cases = collect($statuses)->map(fn (string $status, int $position): string => "WHEN ? THEN {$position}")->implode(' ');

        return $query->orderByRaw(
            'CASE '.$query->getModel()->qualifyColumn('status')." {$cases} ELSE ".count($statuses)." END {$direction}",
            $statuses,
        );
    }
}
