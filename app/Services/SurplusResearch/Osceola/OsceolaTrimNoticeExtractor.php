<?php

namespace App\Services\SurplusResearch\Osceola;

use App\Contracts\SurplusResearch\PdfTextExtractorInterface;
use App\Contracts\SurplusResearch\TrimNoticeExtractorInterface;
use App\Data\SurplusResearch\TrimNoticeData;
use App\Enums\SurplusOwnerResearchStatus;
use App\Exceptions\OwnerResearchException;
use App\Domain\Properties\PropertyNormalizer;
use Illuminate\Support\Str;

class OsceolaTrimNoticeExtractor implements TrimNoticeExtractorInterface
{
    public function __construct(
        private readonly PdfTextExtractorInterface $pdfText,
        private readonly PropertyNormalizer $parcels,
    ) {}

    public function extract(string $pdfContents, string $parcelId, int $year, string $sourceReference): TrimNoticeData
    {
        try {
            $text = $this->pdfText->extract($pdfContents);
        } catch (\Throwable $error) {
            throw new OwnerResearchException(
                "The {$year} TRIM notice could not be converted to text.",
                SurplusOwnerResearchStatus::ManualReview,
                false,
                $error,
            );
        }

        $plain = preg_replace('/\(cid:\d+\)/', '', str_replace(["\r\n", "\r"], "\n", $text)) ?? $text;
        $upper = mb_strtoupper($plain);
        if (! str_contains($upper, 'NOTICE OF PROPOSED PROPERTY TAXES') || ! str_contains($upper, $year.' REAL ESTATE')) {
            throw new OwnerResearchException('The attachment is not the expected Osceola TRIM notice.', SurplusOwnerResearchStatus::ManualReview);
        }
        if (! str_contains((string) $this->parcels->parcelId($plain), (string) $this->parcels->parcelId($parcelId))) {
            throw new OwnerResearchException('The TRIM notice parcel does not match the Surplus case.', SurplusOwnerResearchStatus::ManualReview);
        }

        $lines = collect(preg_split('/\n/u', $plain) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? $line))
            ->filter(fn (string $line): bool => $line !== '' && ! preg_match('/^\(cid:/', $line))
            ->values();
        $siteIndex = $lines->search(fn (string $line): bool => strcasecmp($line, 'SITE ADDRESS:') === 0);
        $boundary = $lines->search(fn (string $line): bool => preg_match('/^PRIOR\s+\d{4}\s+CURRENT\s+'.$year.'$/i', $line) === 1);
        if ($siteIndex === false || $boundary === false || $boundary <= $siteIndex) {
            throw new OwnerResearchException('The TRIM owner block could not be located.', SurplusOwnerResearchStatus::ManualReview);
        }

        $block = $lines->slice($siteIndex + 1, $boundary - $siteIndex - 1)
            ->reject(fn (string $line): bool => preg_match('/^(?:#|-\s*\d+|\d+)$/', $line) === 1)
            ->values();
        $postalIndex = null;
        $mailingCity = $mailingState = $mailingZip = null;
        foreach ($block as $index => $line) {
            if (preg_match('/^(.+?)\s+([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/i', $line, $match)) {
                $postalIndex = $index;
                $mailingCity = Str::title(mb_strtolower(trim($match[1])));
                $mailingState = mb_strtoupper($match[2]);
                $mailingZip = $match[3];
            }
        }

        if ($postalIndex === null || $postalIndex < 3) {
            throw new OwnerResearchException('The TRIM owner and mailing block is ambiguous.', SurplusOwnerResearchStatus::ManualReview);
        }

        $mailingAddress = $block[$postalIndex - 1] ?? null;
        // Osceola's real-property TRIM layout places the two-line situs block first.
        $ownerLines = $block->slice(2, max(0, $postalIndex - 3))->values();
        if ($ownerLines->isEmpty() || $ownerLines->count() > 3) {
            throw new OwnerResearchException('The TRIM owner name could not be isolated safely.', SurplusOwnerResearchStatus::ManualReview);
        }

        $ownerRaw = trim((string) $ownerLines->first());
        $coOwnerRaw = $ownerLines->slice(1)->implode(' ');
        $warning = blank($mailingAddress) ? 'The TRIM notice did not contain a usable mailing street address.' : null;

        return new TrimNoticeData(
            $year, $ownerRaw, $coOwnerRaw !== '' ? $coOwnerRaw : null,
            $mailingAddress, $mailingCity, $mailingState, $mailingZip,
            $sourceReference, hash('sha256', $pdfContents), $pdfContents,
            Str::limit($plain, 20000, ''), $warning,
        );
    }
}
