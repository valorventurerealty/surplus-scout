<?php

namespace App\Contracts;

use App\Data\ContactExtractionResult;
use App\Models\ContactIntakeFile;

interface ContactDocumentExtractionInterface
{
    public function extract(ContactIntakeFile $file): ContactExtractionResult;
}
