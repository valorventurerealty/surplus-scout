<?php

namespace App\Models;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\SurplusCaseStatus;
use App\Models\Concerns\Auditable;
use App\Services\SurplusCaseService;
use Database\Factories\SurplusCaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurplusCase extends Model
{
    /** @use HasFactory<SurplusCaseFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'token', 'case_number', 'status', 'claimant_contact_id', 'property_id', 'assigned_user_id',
        'research_status', 'surplus_availability',
        'source', 'source_name', 'source_url', 'source_report_date', 'source_last_seen_at',
        'last_surplus_research_run_id',
        'state', 'county', 'parcel_id', 'parcel_id_raw', 'normalized_parcel_id', 'clerk_unique_key',
        'tax_deed_number', 'foreclosure_case_number', 'certificate_number',
        'current_owner_raw', 'current_owner_normalized', 'previous_owner_raw', 'previous_owner_normalized',
        'co_owner_raw', 'claimant_mailing_address', 'claimant_mailing_city', 'claimant_mailing_state',
        'claimant_mailing_zip', 'historical_trim_year', 'property_appraiser_address', 'owner_type',
        'property_appraiser_verified', 'historical_owner_verified', 'owner_researched_at', 'owner_research_notes',
        'surplus_amount', 'agreed_fee_percentage', 'expected_fee', 'recovered_amount', 'actual_fee',
        'sale_date', 'claim_deadline', 'agreement_date', 'submitted_at', 'approved_at', 'paid_at',
        'document_drive_url', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SurplusCaseStatus::class,
            'surplus_amount' => 'decimal:2', 'agreed_fee_percentage' => 'decimal:2',
            'expected_fee' => 'decimal:2', 'recovered_amount' => 'decimal:2', 'actual_fee' => 'decimal:2',
            'sale_date' => 'date', 'claim_deadline' => 'date', 'agreement_date' => 'date',
            'submitted_at' => 'date', 'approved_at' => 'date', 'paid_at' => 'date',
            'source_report_date' => 'datetime', 'source_last_seen_at' => 'datetime',
            'property_appraiser_verified' => 'boolean', 'historical_owner_verified' => 'boolean',
            'owner_researched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SurplusCase $case): void {
            $case->normalized_parcel_id = app(PropertyNormalizer::class)->parcelId($case->parcel_id);
            $case->agreed_fee_percentage = number_format(SurplusCaseService::FIXED_FEE_PERCENTAGE, 2, '.', '');
            $case->expected_fee = $case->surplus_amount !== null
                ? number_format(round((float) $case->surplus_amount * SurplusCaseService::FIXED_FEE_PERCENTAGE) / 100, 2, '.', '')
                : null;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function claimantContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'claimant_contact_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)
            ->withPivot(['id', 'role', 'relationship_notes', 'created_by'])
            ->withTimestamps();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function outboundEmails(): MorphMany
    {
        return $this->morphMany(OutboundEmail::class, 'related');
    }

    public function intakeFiles(): HasMany
    {
        return $this->hasMany(SurplusIntakeFile::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', SurplusCaseStatus::Closed->value);
    }

    public function scopeOrderByPipelineStatus(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $statuses = SurplusCaseStatus::orderedValues();
        $cases = collect($statuses)->map(fn (string $status, int $position): string => "WHEN ? THEN {$position}")->implode(' ');

        return $query->orderByRaw(
            'CASE '.$query->getModel()->qualifyColumn('status')." {$cases} ELSE ".count($statuses)." END {$direction}",
            $statuses,
        );
    }
}
