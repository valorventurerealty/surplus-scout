<?php

namespace App\Data\SurplusResearch;

final readonly class PropertyAppraiserRecordData
{
    public function __construct(
        public string $parcelRaw,
        public string $parcelNormalized,
        public string $currentOwnerRaw,
        public string $propertyAddress,
        public string $sourceReference,
    ) {}
}
