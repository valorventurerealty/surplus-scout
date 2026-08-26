<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurplusOwnerResearchEvent extends Model
{
    protected $fillable = ['surplus_owner_research_attempt_id', 'event', 'context', 'occurred_at'];
    protected function casts(): array { return ['context' => 'array', 'occurred_at' => 'datetime']; }
    public function attempt(): BelongsTo { return $this->belongsTo(SurplusOwnerResearchAttempt::class, 'surplus_owner_research_attempt_id'); }
}
