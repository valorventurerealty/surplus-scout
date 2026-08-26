<?php

namespace App\Services\SurplusResearch;

use App\Data\SurplusResearch\CountySurplusRecordData;
use App\Models\SurplusCase;

class SurplusDuplicateService
{
    public function find(CountySurplusRecordData $record): ?SurplusCase
    {
        return SurplusCase::query()->where('clerk_unique_key', $record->uniqueKey)->first()
            ?? SurplusCase::query()
                ->where('state', $record->state)
                ->whereRaw('LOWER(county) = ?', [strtolower($record->county)])
                ->where('normalized_parcel_id', $record->parcelIdNormalized)
                ->where('tax_deed_number', $record->taxDeedNumber)
                ->first();
    }
}
