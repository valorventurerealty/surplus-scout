<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyFinancialSplit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PropertyFinancialService
{
    public function __construct(
        private readonly PropertyFinancialCalculator $calculator,
        private readonly PropertyFinancialDependencySynchronizer $dependencySynchronizer,
    ) {}

    public function update(Property $property, array $data, User $actor): Property
    {
        return DB::transaction(function () use ($property, $data, $actor): Property {
            $property->update([
                ...$this->calculator->calculate($data, $property),
                'updated_by' => $actor->id,
            ]);

            $this->dependencySynchronizer->synchronize($property->refresh(), $actor);

            $split = PropertyFinancialSplit::query()
                ->where('property_id', $property->id)
                ->lockForUpdate()
                ->first() ?? new PropertyFinancialSplit([
                    'property_id' => $property->id,
                    'created_by' => $actor->id,
                ]);

            $split->fill([
                'vvr_percentage' => 20,
                'contact_one_id' => array_key_exists('contact_one_id', $data) ? $data['contact_one_id'] : $split->contact_one_id,
                'contact_one_percentage' => 40,
                'contact_two_id' => array_key_exists('contact_two_id', $data) ? $data['contact_two_id'] : $split->contact_two_id,
                'contact_two_percentage' => 40,
                'updated_by' => $actor->id,
            ])->save();

            return $property->refresh()->load(['financialSplit.contactOne', 'financialSplit.contactTwo']);
        });
    }
}
