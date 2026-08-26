<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\WetlandsStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'parcel_id', 'normalized_parcel_id', 'county', 'normalized_county', 'address', 'city',
        'state', 'postal_code', 'normalized_address', 'property_type',
        'status', 'acreage', 'zoning', 'flood_zone', 'wetlands_status', 'road_access', 'utilities',
        'gis_links', 'document_drive_url', 'closing_documents_url', 'owner_contact_id', 'purchase_price', 'arv', 'wholesale_price',
        'investor_price', 'taxes', 'attorney_fees', 'realtor_fees', 'other_costs', 'all_in_amount', 'expected_sales_price', 'actual_sales_price',
        'expected_profit', 'actual_profit', 'legal_description', 'research_notes',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'status' => PropertyStatus::class,
            'wetlands_status' => WetlandsStatus::class,
            'acreage' => 'decimal:4',
            'purchase_price' => 'decimal:2',
            'arv' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'investor_price' => 'decimal:2',
            'taxes' => 'decimal:2',
            'attorney_fees' => 'decimal:2',
            'realtor_fees' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'all_in_amount' => 'decimal:2',
            'expected_sales_price' => 'decimal:2',
            'actual_sales_price' => 'decimal:2',
            'expected_profit' => 'decimal:2',
            'actual_profit' => 'decimal:2',
            'utilities' => 'array',
            'gis_links' => 'array',
        ];
    }

    public function ownerContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'owner_contact_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->withPivot(['relationship_type', 'created_by'])
            ->withTimestamps();
    }

    public function financialSplit(): HasOne
    {
        return $this->hasOne(PropertyFinancialSplit::class);
    }

    public function negotiationPlans(): HasMany
    {
        return $this->hasMany(NegotiationPlan::class);
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function outboundEmails(): MorphMany
    {
        return $this->morphMany(OutboundEmail::class, 'related');
    }

    public function taxRecords(): HasMany
    {
        return $this->hasMany(PropertyTaxRecord::class);
    }

    public function surplusIntakeFiles(): HasMany
    {
        return $this->hasMany(SurplusIntakeFile::class);
    }

    public function intakeFiles(): HasMany
    {
        return $this->hasMany(PropertyIntakeFile::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(PropertyChecklistItem::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function armorySessions(): HasMany
    {
        return $this->hasMany(ArmorySession::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function surplusCases(): HasMany
    {
        return $this->hasMany(SurplusCase::class);
    }

    public function preAuctionAcquisitions(): HasMany
    {
        return $this->hasMany(PreAuctionAcquisition::class);
    }

    public function scopeOrderByPipelineStatus(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $statuses = PropertyStatus::orderedValues();
        $cases = collect($statuses)
            ->map(fn (string $status, int $position): string => "WHEN ? THEN {$position}")
            ->implode(' ');
        $column = $query->getModel()->qualifyColumn('status');

        return $query->orderByRaw(
            "CASE {$column} {$cases} ELSE ".count($statuses)." END {$direction}",
            $statuses,
        );
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', array_filter([$this->address, $this->city, trim($this->state.' '.$this->postal_code)]));
    }
}
