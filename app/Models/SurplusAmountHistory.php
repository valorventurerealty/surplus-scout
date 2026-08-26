<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurplusAmountHistory extends Model
{
    protected $fillable = ['surplus_case_id', 'research_run_id', 'previous_amount', 'new_amount', 'changed_at'];

    protected function casts(): array
    {
        return ['previous_amount' => 'decimal:2', 'new_amount' => 'decimal:2', 'changed_at' => 'datetime'];
    }

    public function surplusCase(): BelongsTo { return $this->belongsTo(SurplusCase::class); }
    public function researchRun(): BelongsTo { return $this->belongsTo(SurplusResearchRun::class, 'research_run_id'); }
}
