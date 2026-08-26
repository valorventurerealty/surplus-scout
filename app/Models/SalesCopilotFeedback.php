<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesCopilotFeedback extends Model
{
    use Auditable;
    protected $table = 'sales_copilot_feedback';
    protected $fillable = ['sales_copilot_turn_id','user_id','rating','original_response','final_wording','notes','save_to_playbook'];
    protected function casts(): array { return ['save_to_playbook'=>'boolean']; }
    public function turn(): BelongsTo { return $this->belongsTo(SalesCopilotTurn::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
