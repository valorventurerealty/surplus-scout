<?php

namespace App\Services\SurplusResearch\Osceola;

use App\Contracts\SurplusResearch\PdfTextExtractorInterface;
use App\Data\SurplusResearch\CountySurplusReportData;
use Carbon\CarbonImmutable;
use RuntimeException;

class OsceolaPdfParser
{
    public function __construct(
        private readonly PdfTextExtractorInterface $extractor,
        private readonly OsceolaRecordNormalizer $normalizer,
    ) {}

    public function parse(string $pdfContents): CountySurplusReportData
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $this->extractor->extract($pdfContents));
        $this->validateDocument($text);

        $reportDateValue = $this->extractReportDate($text);
        $reportDate = CarbonImmutable::createFromFormat('!n/j/Y', $reportDateValue);
        if ($reportDate === false) {
            throw new RuntimeException('The Clerk report date is invalid.');
        }

        $records = [];
        $warnings = [];
        $failedRows = 0;
        $currentSaleDate = null;

        foreach (preg_split('/\n/', $text) ?: [] as $lineNumber => $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
            if ($line === '') continue;
            if (preg_match('/^(\d{2}\/\d{2}\/\d{4})$/', $line, $dateMatch)) {
                $currentSaleDate = $dateMatch[1];
                continue;
            }

            $row = $this->matchRecord($line);
            if ($row === null) {
                if (str_contains($line, '$') && preg_match('/[A-Z0-9]{12,}/i', $line)) {
                    $failedRows++;
                    $warnings[] = 'Line '.($lineNumber + 1).' looked like a record but could not be safely parsed.';
                }
                continue;
            }

            $rowWarnings = [];
            if ($currentSaleDate === null) $rowWarnings[] = 'Sale date was not available for this record.';
            if (! preg_match('/^\d+-\d{4}$/', $row['tax_deed'])) {
                $rowWarnings[] = 'Tax Deed number has unusual Clerk formatting and was preserved exactly.';
            }
            try {
                $record = $this->normalizer->normalize(
                    $row['parcel'], $row['tax_deed'], $row['certificate'], $currentSaleDate, $row['amount'], $rowWarnings,
                );
                if (isset($records[$record->uniqueKey])) {
                    $warnings[] = 'Duplicate row '.$record->uniqueKey.' appeared in the Clerk report; only one copy was retained.';
                    continue;
                }
                $records[$record->uniqueKey] = $record;
                foreach ($record->warnings as $recordWarning) {
                    $warnings[] = $record->uniqueKey.': '.$recordWarning;
                }
            } catch (\Throwable $exception) {
                $failedRows++;
                $warnings[] = 'Line '.($lineNumber + 1).' was rejected: '.$exception->getMessage();
            }
        }

        if (count($records) < (int) config('surplus_research.osceola.minimum_records', 1)) {
            throw new RuntimeException('The Clerk report did not contain the minimum number of recognizable records.');
        }

        return new CountySurplusReportData($reportDate, array_values($records), $warnings, $failedRows);
    }

    private function extractReportDate(string $text): string
    {
        $patterns = [
            '/Report\s+Date:\s*(?<date>\d{1,2}\/\d{1,2}\/\d{4})(?:\s+\d{1,2}:\d{2}:\d{2}\s+[AP]M)?/i',
            '/(?<date>\d{1,2}\/\d{1,2}\/\d{4})\s+\d{1,2}:\d{2}:\d{2}\s+[AP]M\s+Report\s+Date:/i',
            '/All\s+Dates\s+Through:\s*(?<date>\d{1,2}\/\d{1,2}\/\d{4})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) return $match['date'];
        }

        throw new RuntimeException('The Clerk report date could not be identified.');
    }

    /** @return array{tax_deed:string,certificate:?string,amount:string,parcel:string}|null */
    private function matchRecord(string $line): ?array
    {
        if (! preg_match('/^(?<tax_deed>[A-Z0-9-]+)\s+(?:(?<certificate>[A-Z0-9-]+)\s+)?\$(?<amount>[0-9,]+\.\d{2})(?:\s+.*?)?\s+(?<parcel>[A-Z0-9-]{12,})$/i', $line, $match)) {
            return null;
        }
        return [
            'tax_deed' => $match['tax_deed'], 'certificate' => ($match['certificate'] ?? '') ?: null,
            'amount' => $match['amount'], 'parcel' => $match['parcel'],
        ];
    }

    private function validateDocument(string $text): void
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $required = ['Tax Deeds Surplus Funds Available', 'Sale Date', 'Tax Deed #', 'Cert #', 'Amt Available', 'Property ID #'];
        $missing = array_filter($required, fn (string $value): bool => stripos($text, $value) === false);
        if ($missing !== []) {
            throw new RuntimeException('The downloaded PDF is not the expected Osceola Tax Deed Surplus Funds report.');
        }
        if (stripos($text, 'Osceola County') === false) {
            throw new RuntimeException('The downloaded report does not identify Osceola County.');
        }
    }
}
