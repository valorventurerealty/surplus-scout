<?php

namespace App\Models;

use Database\Factories\ArmoryScriptStepOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArmoryScriptStepOption extends Model
{
    /** @use HasFactory<ArmoryScriptStepOptionFactory> */
    use HasFactory;

    protected $fillable = ['armory_script_step_id', 'label', 'response_text', 'next_step_id', 'outcome_code', 'sequence'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'next_step_id' => 'integer'];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ArmoryScriptStep::class, 'armory_script_step_id');
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(ArmoryScriptStep::class, 'next_step_id');
    }

}
