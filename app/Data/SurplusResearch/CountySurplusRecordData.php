<?php

namespace App\Data\SurplusResearch;

use Carbon\CarbonImmutable;

final readonly class CountySurplusRecordData
{
    /** @param list<string> $warnings */
    public function __construct(
        public string $county,
        public string $state,
        public string $parcelIdRaw,
        public string $parcelIdNormalized,
        public string $taxDeedNumber,
        public ?string $certificateNumber,
        public ?CarbonImmutable $saleDate,
        public string $surplusAmount,
        public string $uniqueKey,
        public array $warnings = [],
    ) {}
}
