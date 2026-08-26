<?php

namespace App\Models;

use App\Enums\ProjectionScenarioStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ProjectionScenarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectionScenario extends Model
{
    /** @use HasFactory<ProjectionScenarioFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'token', 'name', 'status', 'start_year', 'end_year', 'contact_one_id',
        'contact_two_id', 'is_default', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectionScenarioStatus::class,
            'start_year' => 'integer',
            'end_year' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /** @return list<int> */
    public function years(): array
    {
        return range($this->start_year, $this->end_year);
    }

    public function assumptions(): HasMany
    {
        return $this->hasMany(ProjectionAssumption::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ProjectionEntry::class);
    }

    public function contactOne(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_one_id');
    }

    public function contactTwo(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_two_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
