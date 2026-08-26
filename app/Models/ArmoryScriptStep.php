<?php

namespace App\Models;

use Database\Factories\ArmoryScriptStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArmoryScriptStep extends Model
{
    /** @use HasFactory<ArmoryScriptStepFactory> */
    use HasFactory;

    protected $fillable = ['armory_script_id', 'title', 'prompt_text', 'guidance_text', 'sequence', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    public function script(): BelongsTo
    {
        return $this->belongsTo(ArmoryScript::class, 'armory_script_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ArmoryScriptStepOption::class)->orderBy('sequence');
    }
}
