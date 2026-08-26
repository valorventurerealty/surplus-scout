<?php

namespace App\Services;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PropertyPipelineService
{
    public function move(Property $property, PropertyStatus $status, User $actor): Property
    {
        return DB::transaction(function () use ($property, $status, $actor): Property {
            $property = Property::query()->lockForUpdate()->findOrFail($property->id);

            if ($property->status !== $status) {
                $property->update([
                    'status' => $status,
                    'updated_by' => $actor->id,
                ]);
            }

            return $property->refresh();
        });
    }
}
