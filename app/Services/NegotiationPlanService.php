<?php

namespace App\Services;

use App\Models\NegotiationPlan;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NegotiationPlanService
{
    public function create(array $data, User $actor): NegotiationPlan
    {
        $data['sync_property_financials'] ??= ! empty($data['property_id']);
        $data = $this->synchronizeLinkedValues($data);

        return DB::transaction(fn (): NegotiationPlan => NegotiationPlan::query()->create([
            ...$data,
            'vvr_percentage' => 20,
            'investor_one_percentage' => 40,
            'investor_two_percentage' => 40,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]));
    }

    public function update(NegotiationPlan $negotiation, array $data, User $actor): NegotiationPlan
    {
        $data['sync_property_financials'] ??= $negotiation->sync_property_financials;
        $data = $this->synchronizeLinkedValues($data);

        return DB::transaction(function () use ($negotiation, $data, $actor): NegotiationPlan {
            $negotiation->update([
                ...$data,
                'vvr_percentage' => 20,
                'investor_one_percentage' => 40,
                'investor_two_percentage' => 40,
                'updated_by' => $actor->id,
            ]);

            return $negotiation->refresh();
        });
    }

    private function synchronizeLinkedValues(array $data): array
    {
        if (! ($data['sync_property_financials'] ?? false) || empty($data['property_id'])) {
            $data['financials_synced_at'] = null;

            return $data;
        }

        $property = Property::query()->findOrFail($data['property_id']);

        if ($property->expected_sales_price !== null && (float) $property->expected_sales_price > 0) {
            $data['asking_price'] = $property->expected_sales_price;
        }

        if ($property->all_in_amount !== null && (float) $property->all_in_amount > 0) {
            $data['all_in_amount'] = $property->all_in_amount;
        }

        $data['financials_synced_at'] = now();

        return $data;
    }
}
