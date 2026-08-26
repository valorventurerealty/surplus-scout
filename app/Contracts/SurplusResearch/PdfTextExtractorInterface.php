<?php

namespace App\Contracts\SurplusResearch;

interface PdfTextExtractorInterface
{
    public function extract(string $pdfContents): string;
}
