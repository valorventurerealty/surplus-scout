<?php

namespace App\Models;

use App\Enums\ProjectionCategory;
use App\Models\Concerns\Auditable;
use Database\Factories\ProjectionEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectionEntry extends Model
{
    /** @use HasFactory<ProjectionEntryFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'projection_scenario_id', 'category', 'year', 'month', 'projected_units',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => ProjectionCategory::class,
            'year' => 'integer',
            'month' => 'integer',
            'projected_units' => 'integer',
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ProjectionScenario::class, 'projection_scenario_id');
    }
}
