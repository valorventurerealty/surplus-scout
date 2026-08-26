<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArmorySessionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['armory_session_id', 'armory_script_step_id', 'armory_script_step_option_id', 'event_type', 'payload'];

    protected function casts(): array { return ['payload' => 'array']; }
    public function session(): BelongsTo { return $this->belongsTo(ArmorySession::class, 'armory_session_id'); }
    public function step(): BelongsTo { return $this->belongsTo(ArmoryScriptStep::class, 'armory_script_step_id'); }
    public function option(): BelongsTo { return $this->belongsTo(ArmoryScriptStepOption::class, 'armory_script_step_option_id'); }
}
