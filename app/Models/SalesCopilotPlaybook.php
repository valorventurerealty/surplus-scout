<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SalesCopilotPlaybook extends Model
{
    use Auditable, HasUuids, SoftDeletes;
    public function uniqueIds(): array { return ['token']; }
    protected $fillable = ['token','title','slug','category','scenario','prospect_type','trigger_phrases','recommended_response','tones','objective','stage','follow_up_questions','branches','listen_for','mistakes_to_avoid','notes','priority','active','vvr_approved','owner_authored','source_reference','created_by','updated_by'];
    protected function casts(): array { return ['trigger_phrases'=>'array','tones'=>'array','follow_up_questions'=>'array','branches'=>'array','listen_for'=>'array','mistakes_to_avoid'=>'array','active'=>'boolean','vvr_approved'=>'boolean','owner_authored'=>'boolean']; }
    public function getRouteKeyName(): string { return 'token'; }
    public function turns(): HasMany { return $this->hasMany(SalesCopilotTurn::class, 'matched_playbook_id'); }
}
