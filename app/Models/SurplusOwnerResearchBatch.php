<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurplusOwnerResearchBatch extends Model
{
    protected $fillable = [
        'token', 'county', 'mode', 'status', 'total_cases', 'processed_cases', 'verified_owners',
        'ready_for_skip_trace', 'business_research_needed', 'estate_research_needed',
        'trust_research_needed', 'manual_review', 'errors', 'case_ids', 'error_message',
        'started_at', 'completed_at', 'triggered_by',
    ];

    protected function casts(): array
    {
        return ['case_ids' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string { return 'token'; }
    public function triggeredBy(): BelongsTo { return $this->belongsTo(User::class, 'triggered_by'); }
    public function attempts(): HasMany { return $this->hasMany(SurplusOwnerResearchAttempt::class); }
}
