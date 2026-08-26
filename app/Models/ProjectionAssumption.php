<?php

namespace App\Models;

use App\Enums\ProjectionCategory;
use App\Models\Concerns\Auditable;
use Database\Factories\ProjectionAssumptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectionAssumption extends Model
{
    /** @use HasFactory<ProjectionAssumptionFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'projection_scenario_id', 'category', 'average_net_profit', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => ProjectionCategory::class,
            'average_net_profit' => 'decimal:2',
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ProjectionScenario::class, 'projection_scenario_id');
    }
}
