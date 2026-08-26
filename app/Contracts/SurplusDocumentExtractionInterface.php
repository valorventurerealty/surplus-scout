<?php

namespace App\Contracts;

use App\Data\SurplusExtractionResult;
use App\Models\SurplusIntakeFile;

interface SurplusDocumentExtractionInterface
{
    public function extract(SurplusIntakeFile $file): SurplusExtractionResult;
}
