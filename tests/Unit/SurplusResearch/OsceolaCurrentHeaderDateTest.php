<?php

namespace Tests\Unit\SurplusResearch;

use App\Contracts\SurplusResearch\PdfTextExtractorInterface;
use App\Domain\Properties\PropertyNormalizer;
use App\Services\SurplusResearch\Osceola\OsceolaPdfParser;
use App\Services\SurplusResearch\Osceola\OsceolaRecordNormalizer;
use Tests\TestCase;

class OsceolaCurrentHeaderDateTest extends TestCase
{
    public function test_current_clerk_header_with_date_before_label_is_parsed(): void
    {
        $text = "Tax Deeds Surplus Funds Available\n8/24/2026 7:55:04 AM Report Date:\nSale Date Tax Deed # Cert # Amt Available Previous Owner of Record Property ID #\n08/18/2026\n69-2026 61362024 $8,121.74 3627316000000L1620\nClerk of the Circuit Court of Osceola County, Florida";
        $extractor = new class($text) implements PdfTextExtractorInterface {
            public function __construct(private readonly string $text) {}
            public function extract(string $pdfContents): string { return $this->text; }
        };
        $report = (new OsceolaPdfParser($extractor, new OsceolaRecordNormalizer(new PropertyNormalizer)))->parse('%PDF-test');
        $this->assertSame('2026-08-24', $report->reportDate->format('Y-m-d'));
        $this->assertCount(1, $report->records);
    }
}
