<?php

namespace Tests\Unit\SurplusResearch;

use App\Contracts\SurplusResearch\PdfTextExtractorInterface;
use App\Domain\Properties\PropertyNormalizer;
use App\Services\SurplusResearch\Osceola\OsceolaPdfParser;
use App\Services\SurplusResearch\Osceola\OsceolaRecordNormalizer;
use RuntimeException;
use Tests\TestCase;

class OsceolaPdfParserTest extends TestCase
{
    public function test_it_parses_valid_rows_currency_and_normalizes_parcels_without_inventing_formatting(): void
    {
        $report = $this->parser(<<<'TEXT'
Tax Deeds Surplus Funds Available
Report Date: 8/24/2026 7:55:04 AM
Sale Date Tax Deed # Cert # Amt Available Previous Owner of Record Property ID #
Clerk of the Circuit Court of Osceola County, Florida
08/20/2026
69-2026 61362024 $8,121.74 36-27-31-6000-000L-1620
1662025 28202019 $2,267.77 122731000024130000
TEXT)->parse('%PDF-fake');

        $this->assertCount(2, $report->records);
        $this->assertSame('3627316000000L1620', $report->records[0]->parcelIdNormalized);
        $this->assertSame('8121.74', $report->records[0]->surplusAmount);
        $this->assertSame('OSCEOLA|3627316000000L1620|69-2026', $report->records[0]->uniqueKey);
        $this->assertSame('1662025', $report->records[1]->taxDeedNumber);
        $this->assertNotEmpty($report->records[1]->warnings);
    }

    public function test_it_rejects_an_unexpected_document(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser('An unrelated PDF')->parse('%PDF-fake');
    }

    public function test_it_flags_an_invalid_row_and_does_not_create_guessed_data(): void
    {
        $report = $this->parser(<<<'TEXT'
Tax Deeds Surplus Funds Available
Report Date: 8/24/2026
Sale Date Tax Deed # Cert # Amt Available Previous Owner of Record Property ID #
Clerk of the Circuit Court of Osceola County, Florida
08/20/2026
69-2026 61362024 $8,121.74 3627316000000L1620
$2,000.00 UNKNOWNPROPERTY123
TEXT)->parse('%PDF-fake');
        $this->assertCount(1, $report->records);
        $this->assertSame(1, $report->failedRows);
    }

    public function test_missing_optional_certificate_remains_null(): void
    {
        $report = $this->parser(<<<'TEXT'
Tax Deeds Surplus Funds Available
Report Date: 8/24/2026
Sale Date Tax Deed # Cert # Amt Available Previous Owner of Record Property ID #
Clerk of the Circuit Court of Osceola County, Florida
08/20/2026
70-2026 $1,000.00 3627316000000L9999
TEXT)->parse('%PDF-fake');
        $this->assertNull($report->records[0]->certificateNumber);
    }

    private function parser(string $text): OsceolaPdfParser
    {
        $extractor = new class($text) implements PdfTextExtractorInterface {
            public function __construct(private readonly string $text) {}
            public function extract(string $pdfContents): string { return $this->text; }
        };
        return new OsceolaPdfParser($extractor, new OsceolaRecordNormalizer(new PropertyNormalizer));
    }
}
