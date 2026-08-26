<?php

namespace App\Contracts;

use App\Data\PropertyExtractionResult;
use App\Models\PropertyIntakeFile;

interface PropertyDocumentExtractionInterface
{
    public function extract(PropertyIntakeFile $file): PropertyExtractionResult;
}
