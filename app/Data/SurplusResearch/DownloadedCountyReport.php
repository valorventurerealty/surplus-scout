<?php

namespace App\Data\SurplusResearch;

final readonly class DownloadedCountyReport
{
    public function __construct(
        public string $contents,
        public string $sourceUrl,
        public string $sha256,
        public int $size,
    ) {}
}
