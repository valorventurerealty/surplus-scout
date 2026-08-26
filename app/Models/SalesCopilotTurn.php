<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCopilotTurn extends Model
{
    use Auditable;
    protected $fillable = ['sales_copilot_session_id','sequence','prospect_statement','salesperson_previous','classification','classification_confidence','conversation_stage','resistance_level','response','matched_playbook_id','provider_response_id','input_tokens','output_tokens','latency_ms','used_fallback','requires_human_review','requires_legal_review'];
    protected function casts(): array { return ['classification_confidence'=>'decimal:4','response'=>'array','used_fallback'=>'boolean','requires_human_review'=>'boolean','requires_legal_review'=>'boolean']; }
    public function session(): BelongsTo { return $this->belongsTo(SalesCopilotSession::class, 'sales_copilot_session_id'); }
    public function playbook(): BelongsTo { return $this->belongsTo(SalesCopilotPlaybook::class, 'matched_playbook_id'); }
    public function feedback(): HasMany { return $this->hasMany(SalesCopilotFeedback::class); }
}
