<?php

namespace App\Services;

use App\Enums\PropertyChecklistKey;
use App\Models\Property;
use App\Models\PropertyChecklistItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PropertyChecklistService
{
    public function initialize(Property $property, ?User $actor = null): void
    {
        $now = now();
        $rows = collect(PropertyChecklistKey::cases())->map(fn (PropertyChecklistKey $key): array => [
            'property_id' => $property->id,
            'item_key' => $key->value,
            'is_completed' => false,
            'updated_by' => $actor?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        PropertyChecklistItem::query()->insertOrIgnore($rows);
    }

    /** @return Collection<int, PropertyChecklistItem> */
    public function forProperty(Property $property): Collection
    {
        $stored = $property->checklistItems()->with('completedBy:id,name')->get()->keyBy(
            fn (PropertyChecklistItem $item): string => $item->item_key->value
        );

        return collect(PropertyChecklistKey::cases())->map(function (PropertyChecklistKey $key) use ($property, $stored): PropertyChecklistItem {
            return $stored->get($key->value) ?? new PropertyChecklistItem([
                'property_id' => $property->id,
                'item_key' => $key,
                'is_completed' => false,
            ]);
        });
    }

    public function update(Property $property, array $items, User $actor, bool $mayUpdateLinks): void
    {
        DB::transaction(function () use ($property, $items, $actor, $mayUpdateLinks): void {
            $this->initialize($property, $actor);
            $stored = $property->checklistItems()->lockForUpdate()->get()->keyBy(
                fn (PropertyChecklistItem $item): string => $item->item_key->value
            );

            foreach (PropertyChecklistKey::cases() as $key) {
                $item = $stored->get($key->value);
                $input = $items[$key->value];
                $completed = (bool) $input['completed'];
                $changes = [
                    'is_completed' => $completed,
                    'updated_by' => $actor->id,
                ];

                if ($completed && ! $item->is_completed) {
                    $changes['completed_at'] = now();
                    $changes['completed_by'] = $actor->id;
                } elseif (! $completed) {
                    $changes['completed_at'] = null;
                    $changes['completed_by'] = null;
                }

                if ($mayUpdateLinks && array_key_exists('evidence_url', $input)) {
                    $changes['evidence_url'] = filled($input['evidence_url'])
                        ? trim((string) $input['evidence_url'])
                        : null;
                }

                $item->update($changes);
            }
        });
    }
}
