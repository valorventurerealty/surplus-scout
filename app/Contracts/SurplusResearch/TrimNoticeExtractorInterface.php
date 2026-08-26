<?php

namespace App\Contracts\SurplusResearch;

use App\Data\SurplusResearch\TrimNoticeData;

interface TrimNoticeExtractorInterface
{
    public function extract(string $pdfContents, string $parcelId, int $year, string $sourceReference): TrimNoticeData;
}
