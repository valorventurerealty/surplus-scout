<?php

namespace App\Contracts\SurplusResearch;

use App\Data\SurplusResearch\DownloadedCountyReport;

interface CountySurplusSourceInterface
{
    public function download(): DownloadedCountyReport;
}
