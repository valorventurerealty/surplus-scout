<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurplusOwnerResearchAttempt extends Model
{
    protected $fillable = [
        'surplus_owner_research_batch_id', 'surplus_case_id', 'attempt_number', 'status',
        'parcel_searched', 'parcel_returned', 'current_owner_found', 'trim_years_checked',
        'selected_trim_year', 'historical_owner_found', 'classification',
        'property_source_reference', 'trim_source_reference', 'trim_file_disk', 'trim_file_path',
        'trim_file_hash', 'extracted_text_excerpt', 'browser_error', 'extraction_warning',
        'research_notes', 'diagnostic_reference', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['trim_years_checked' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function batch(): BelongsTo { return $this->belongsTo(SurplusOwnerResearchBatch::class, 'surplus_owner_research_batch_id'); }
    public function surplusCase(): BelongsTo { return $this->belongsTo(SurplusCase::class); }
    public function events(): HasMany { return $this->hasMany(SurplusOwnerResearchEvent::class); }
}
