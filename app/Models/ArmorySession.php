<?php

namespace App\Models;

use App\Enums\ArmorySessionStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ArmorySessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArmorySession extends Model
{
    /** @use HasFactory<ArmorySessionFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['token', 'armory_script_id', 'user_id', 'contact_id', 'property_id', 'current_step_id', 'status', 'caller_name', 'notes', 'outcome', 'started_at', 'completed_at'];

    public function auditExcludedAttributes(): array
    {
        return ['notes'];
    }

    protected function casts(): array
    {
        return ['status' => ArmorySessionStatus::class, 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function script(): BelongsTo { return $this->belongsTo(ArmoryScript::class, 'armory_script_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function currentStep(): BelongsTo { return $this->belongsTo(ArmoryScriptStep::class, 'current_step_id'); }
    public function events(): HasMany { return $this->hasMany(ArmorySessionEvent::class); }
}
