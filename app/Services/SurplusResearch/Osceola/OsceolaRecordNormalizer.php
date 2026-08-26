<?php

namespace App\Services\SurplusResearch\Osceola;

use App\Data\SurplusResearch\CountySurplusRecordData;
use App\Domain\Properties\PropertyNormalizer;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class OsceolaRecordNormalizer
{
    public function __construct(private readonly PropertyNormalizer $properties) {}

    /** @param list<string> $warnings */
    public function normalize(string $parcel, string $taxDeed, ?string $certificate, ?string $saleDate, string $amount, array $warnings = []): CountySurplusRecordData
    {
        $parcelRaw = trim($parcel);
        $parcelNormalized = $this->properties->parcelId($parcelRaw);
        $taxDeed = strtoupper(trim($taxDeed));
        if (! $parcelNormalized || $taxDeed === '') throw new InvalidArgumentException('Parcel ID and Tax Deed number are required identifiers.');

        $money = str_replace([',', '$', ' '], '', $amount);
        if (! preg_match('/^\d+\.\d{2}$/', $money)) throw new InvalidArgumentException('The surplus amount is not a valid currency value.');
        [$whole, $cents] = explode('.', $money, 2);
        $money = (ltrim($whole, '0') ?: '0').'.'.$cents;
        $date = $saleDate ? CarbonImmutable::createFromFormat('m/d/Y', $saleDate)->startOfDay() : null;

        return new CountySurplusRecordData(
            county: 'Osceola', state: 'FL', parcelIdRaw: $parcelRaw,
            parcelIdNormalized: $parcelNormalized, taxDeedNumber: $taxDeed,
            certificateNumber: filled($certificate) ? strtoupper(trim((string) $certificate)) : null,
            saleDate: $date, surplusAmount: $money,
            uniqueKey: 'OSCEOLA|'.$parcelNormalized.'|'.$taxDeed, warnings: $warnings,
        );
    }
}
