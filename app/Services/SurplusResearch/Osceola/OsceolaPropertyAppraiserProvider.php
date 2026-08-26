<?php

namespace App\Services\SurplusResearch\Osceola;

use App\Contracts\SurplusResearch\CountyOwnerResearchProviderInterface;
use App\Contracts\SurplusResearch\TrimNoticeExtractorInterface;
use App\Data\SurplusResearch\PropertyAppraiserRecordData;
use App\Data\SurplusResearch\TrimNoticeData;
use App\Domain\Properties\PropertyNormalizer;
use App\Enums\SurplusOwnerResearchStatus;
use App\Exceptions\OwnerResearchException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OsceolaPropertyAppraiserProvider implements CountyOwnerResearchProviderInterface
{
    private static float $lastRequestAt = 0.0;

    public function __construct(
        private readonly PropertyNormalizer $parcels,
        private readonly TrimNoticeExtractorInterface $trimExtractor,
    ) {}

    public function findProperty(string $parcelId): PropertyAppraiserRecordData
    {
        $normalized = (string) $this->parcels->parcelId($parcelId);
        if ($normalized === '') {
            throw new OwnerResearchException('The Surplus case has no usable parcel ID.', SurplusOwnerResearchStatus::ManualReview);
        }

        $response = $this->get('/api/v1/parcelmarket', ['$filter' => "strap eq '{$normalized}'"]);
        $rows = $response->json('value');
        if (! is_array($rows) || $rows === []) {
            throw new OwnerResearchException('No exact Osceola Property Appraiser parcel was returned.', SurplusOwnerResearchStatus::ParcelNotFound);
        }
        if (count($rows) !== 1) {
            throw new OwnerResearchException('The exact parcel lookup returned multiple records.', SurplusOwnerResearchStatus::ManualReview);
        }

        $row = $rows[0];
        $returned = trim((string) ($row['strap'] ?? ''));
        if ($this->parcels->parcelId($returned) !== $normalized) {
            throw new OwnerResearchException('The returned Property Appraiser parcel did not exactly match the case.', SurplusOwnerResearchStatus::ParcelNotFound);
        }
        $owner = trim(html_entity_decode((string) ($row['Owners'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($owner === '') {
            throw new OwnerResearchException('The Property Appraiser record did not contain a current owner.', SurplusOwnerResearchStatus::ManualReview);
        }

        return new PropertyAppraiserRecordData(
            $returned, $normalized, $owner,
            trim(html_entity_decode((string) ($row['Situs'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $this->baseUrl().'/Search/MainSearch?pin='.rawurlencode($normalized),
        );
    }

    public function findTrimNotice(string $parcelId, int $year): ?TrimNoticeData
    {
        $normalized = (string) $this->parcels->parcelId($parcelId);
        $response = $this->get('/api/v1/attachment', [
            '$select' => 'Id,yr,strap,tp',
            '$filter' => "strap eq '{$normalized}' and yr eq {$year} and tp eq 'TR'",
            '$top' => 2,
        ]);
        $rows = $response->json('value');
        if (! is_array($rows) || $rows === []) return null;
        if (count($rows) !== 1) {
            throw new OwnerResearchException("Multiple {$year} TRIM attachments were returned.", SurplusOwnerResearchStatus::ManualReview);
        }
        $id = $rows[0]['Id'] ?? $rows[0]['id'] ?? null;
        if (! is_int($id) && ! ctype_digit((string) $id)) {
            throw new OwnerResearchException("The {$year} TRIM attachment has no valid identifier.", SurplusOwnerResearchStatus::ManualReview);
        }

        $path = '/Search/GetAttachment/'.rawurlencode((string) $id);
        $pdf = $this->get($path)->body();
        if (! str_starts_with($pdf, '%PDF-') || strlen($pdf) > (int) config('surplus_research.owner_research.max_trim_file_bytes', 10485760)) {
            throw new OwnerResearchException("The {$year} TRIM attachment was not a valid PDF.", SurplusOwnerResearchStatus::ManualReview);
        }

        return $this->trimExtractor->extract($pdf, $normalized, $year, $this->baseUrl().$path);
    }

    private function get(string $path, array $query = []): Response
    {
        $this->paceRequests();
        try {
            $response = Http::acceptJson()->withHeaders(['User-Agent' => 'VVR Command Center/1.0 (public-record research)'])
                ->timeout((int) config('surplus_research.owner_research.request_timeout', 25))
                ->retry((int) config('surplus_research.owner_research.request_retries', 2), 750, throw: false)
                ->get($this->baseUrl().$path, $query);
        } catch (ConnectionException $error) {
            throw new OwnerResearchException('The Osceola Property Appraiser could not be reached.', SurplusOwnerResearchStatus::PropertyAppraiserError, true, $error);
        }

        if (in_array($response->status(), [403, 429], true) || str_contains(mb_strtolower($response->body()), 'captcha')) {
            throw new OwnerResearchException('The Property Appraiser blocked the automated request or requested CAPTCHA review.', SurplusOwnerResearchStatus::PropertyAppraiserError);
        }
        if (! $response->successful()) {
            throw new OwnerResearchException('The Property Appraiser returned HTTP '.$response->status().'.', SurplusOwnerResearchStatus::PropertyAppraiserError, $response->serverError());
        }

        return $response;
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) config('surplus_research.owner_research.osceola_base_url'), '/');
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'search.property-appraiser.org') {
            throw new OwnerResearchException('The Osceola Property Appraiser URL is not authorized.', SurplusOwnerResearchStatus::PropertyAppraiserError);
        }
        return $url;
    }

    private function paceRequests(): void
    {
        $minimum = max(0, (int) config('surplus_research.owner_research.minimum_request_interval_ms', 1500)) / 1000;
        $remaining = $minimum - (microtime(true) - self::$lastRequestAt);
        if ($remaining > 0) usleep((int) ($remaining * 1_000_000));
        self::$lastRequestAt = microtime(true);
    }
}
