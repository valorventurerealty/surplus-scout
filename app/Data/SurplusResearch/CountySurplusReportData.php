<?php

namespace App\Data\SurplusResearch;

use Carbon\CarbonImmutable;

final readonly class CountySurplusReportData
{
    /** @param list<CountySurplusRecordData> $records @param list<string> $warnings */
    public function __construct(
        public CarbonImmutable $reportDate,
        public array $records,
        public array $warnings,
        public int $failedRows,
    ) {}
}
