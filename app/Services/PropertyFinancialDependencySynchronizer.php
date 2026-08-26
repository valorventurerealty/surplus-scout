<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;

class PropertyFinancialDependencySynchronizer
{
    public function synchronize(Property $property, User $actor): void
    {
        $updates = array_filter([
            'asking_price' => $property->expected_sales_price,
            'all_in_amount' => $property->all_in_amount,
        ], fn (mixed $value): bool => $value !== null && (float) $value > 0);

        if ($updates === []) {
            return;
        }

        $property->negotiationPlans()
            ->where('sync_property_financials', true)
            ->eachById(function ($negotiation) use ($updates, $actor): void {
                $negotiation->update([
                    ...$updates,
                    'financials_synced_at' => now(),
                    'updated_by' => $actor->id,
                ]);
            });
    }
}
