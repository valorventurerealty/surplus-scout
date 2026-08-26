<?php

namespace App\Services;

use App\Enums\ProjectionCategory;
use App\Models\ProjectionScenario;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectionScenarioService
{
    public function create(array $data, User $actor): ProjectionScenario
    {
        return DB::transaction(function () use ($data, $actor): ProjectionScenario {
            $makeDefault = ! ProjectionScenario::query()->exists();
            $scenario = ProjectionScenario::query()->create([
                ...Arr::only($data, ['name', 'status', 'start_year', 'end_year', 'contact_one_id', 'contact_two_id', 'notes']),
                'token' => (string) Str::uuid(),
                'is_default' => $makeDefault,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->ensureGrid($scenario, $actor);

            return $scenario->refresh();
        });
    }

    public function update(ProjectionScenario $scenario, array $data, User $actor): ProjectionScenario
    {
        return DB::transaction(function () use ($scenario, $data, $actor): ProjectionScenario {
            $scenario->update([
                ...Arr::only($data, ['name', 'status', 'start_year', 'end_year', 'contact_one_id', 'contact_two_id', 'notes']),
                'updated_by' => $actor->id,
            ]);

            foreach (ProjectionCategory::cases() as $category) {
                $assumption = $scenario->assumptions()->firstOrNew(['category' => $category->value]);
                if (! $assumption->exists) {
                    $assumption->created_by = $actor->id;
                }
                $assumption->fill([
                    'average_net_profit' => data_get($data, 'assumptions.'.$category->value, $category->defaultAverageNetProfit()),
                    'updated_by' => $actor->id,
                ])->save();
                foreach ($scenario->years() as $year) {
                    foreach (range(1, 12) as $month) {
                        $entry = $scenario->entries()->firstOrNew([
                            'category' => $category->value,
                            'year' => $year,
                            'month' => $month,
                        ]);
                        if (! $entry->exists) {
                            $entry->created_by = $actor->id;
                        }
                        $entry->fill([
                            'projected_units' => (int) data_get($data, "entries.{$category->value}.{$year}.{$month}", 0),
                            'updated_by' => $actor->id,
                        ])->save();
                    }
                }
            }

            return $scenario->refresh();
        });
    }

    public function makeDefault(ProjectionScenario $scenario, User $actor): void
    {
        DB::transaction(function () use ($scenario, $actor): void {
            foreach (ProjectionScenario::query()->where('is_default', true)->where('id', '!=', $scenario->id)->lockForUpdate()->get() as $other) {
                $other->update(['is_default' => false, 'updated_by' => $actor->id]);
            }
            $scenario->update(['is_default' => true, 'updated_by' => $actor->id]);
        });
    }

    public function archive(ProjectionScenario $scenario, User $actor): void
    {
        DB::transaction(function () use ($scenario, $actor): void {
            $wasDefault = $scenario->is_default;
            $scenario->update(['is_default' => false, 'updated_by' => $actor->id]);
            $scenario->delete();
            if ($wasDefault) {
                ProjectionScenario::query()->latest('updated_at')->first()?->update([
                    'is_default' => true,
                    'updated_by' => $actor->id,
                ]);
            }
        });
    }

    private function ensureGrid(ProjectionScenario $scenario, User $actor): void
    {
        foreach (ProjectionCategory::cases() as $category) {
            $scenario->assumptions()->create([
                'category' => $category,
                'average_net_profit' => $category->defaultAverageNetProfit(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            foreach ($scenario->years() as $year) {
                foreach (range(1, 12) as $month) {
                    $scenario->entries()->create([
                        'category' => $category,
                        'year' => $year,
                        'month' => $month,
                        'projected_units' => 0,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                }
            }
        }
    }
}
