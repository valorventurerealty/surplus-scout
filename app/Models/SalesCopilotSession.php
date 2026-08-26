<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SalesCopilotSession extends Model
{
    use Auditable, HasUuids;
    public function uniqueIds(): array { return ['token']; }
    protected $fillable = ['token','user_id','contact_id','surplus_case_id','prospect_name','call_type','prospect_relationship','current_stage','resistance_level','county','parcel_id','estimated_surplus','previous_conversation_summary','additional_notes','state','status','last_coached_at','completed_at'];
    protected function casts(): array { return ['estimated_surplus'=>'decimal:2','state'=>'array','last_coached_at'=>'datetime','completed_at'=>'datetime']; }
    public function getRouteKeyName(): string { return 'token'; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function surplusCase(): BelongsTo { return $this->belongsTo(SurplusCase::class); }
    public function turns(): HasMany { return $this->hasMany(SalesCopilotTurn::class)->orderBy('sequence'); }
}
