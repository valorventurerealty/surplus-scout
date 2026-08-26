<?php

namespace Tests\Unit\SurplusResearch;

use App\Contracts\SurplusResearch\PdfTextExtractorInterface;
use App\Domain\Properties\PropertyNormalizer;
use App\Services\SurplusResearch\Osceola\OsceolaTrimNoticeExtractor;
use PHPUnit\Framework\TestCase;

class OsceolaTrimNoticeExtractorTest extends TestCase
{
    public function test_structured_owner_and_mailing_block_is_extracted_without_invention(): void
    {
        $text = <<<'TEXT'
NOTICE OF PROPOSED PROPERTY TAXES AND
2025 REAL ESTATE THIS IS NOT A BILL
3627316000000L1620 LEGAL DESCRIPTION:
SITE ADDRESS:
HOLOPAW GROVES RD
SAINT CLOUD
ORTIZ NANETTE
765 HUDSON ST APT A
KISSIMMEE FL 34741-7108
PRIOR 2024 CURRENT 2025
TEXT;
        $pdf = new class($text) implements PdfTextExtractorInterface {
            public function __construct(private string $text) {}
            public function extract(string $pdfContents): string { return $this->text; }
        };
        $result = (new OsceolaTrimNoticeExtractor($pdf, new PropertyNormalizer))
            ->extract('%PDF-test', '36-27-31-6000-000L-1620', 2025, 'https://example.test/trim/1');

        $this->assertSame('ORTIZ NANETTE', $result->ownerRaw);
        $this->assertSame('765 HUDSON ST APT A', $result->mailingAddress);
        $this->assertSame('Kissimmee', $result->mailingCity);
        $this->assertSame('FL', $result->mailingState);
        $this->assertSame('34741-7108', $result->mailingZip);
        $this->assertNull($result->coOwnerRaw);
    }
}
