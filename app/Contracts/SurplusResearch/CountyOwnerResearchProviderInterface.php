<?php

namespace App\Contracts\SurplusResearch;

use App\Data\SurplusResearch\PropertyAppraiserRecordData;
use App\Data\SurplusResearch\TrimNoticeData;

interface CountyOwnerResearchProviderInterface
{
    public function findProperty(string $parcelId): PropertyAppraiserRecordData;

    public function findTrimNotice(string $parcelId, int $year): ?TrimNoticeData;
}
