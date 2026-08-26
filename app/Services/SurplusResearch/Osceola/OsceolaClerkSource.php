<?php

namespace App\Services\SurplusResearch\Osceola;

use App\Contracts\SurplusResearch\CountySurplusSourceInterface;
use App\Data\SurplusResearch\DownloadedCountyReport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OsceolaClerkSource implements CountySurplusSourceInterface
{
    public function download(): DownloadedCountyReport
    {
        $sourceUrl = (string) config('surplus_research.osceola.source_url');
        if (parse_url($sourceUrl, PHP_URL_SCHEME) !== 'https' || parse_url($sourceUrl, PHP_URL_HOST) !== 'courts.osceolaclerk.com') {
            throw new RuntimeException('The configured Osceola Clerk source URL is not authorized.');
        }

        $downloadUrl = (string) (config('surplus_research.osceola.download_url') ?: $sourceUrl);
        if (parse_url($downloadUrl, PHP_URL_SCHEME) !== 'https' || ! filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('The configured Osceola report download URL must be a valid HTTPS URL.');
        }
        $usingRelay = $downloadUrl !== $sourceUrl;
        $relayToken = (string) config('surplus_research.osceola.relay_token');
        if ($usingRelay && strlen($relayToken) < 32) {
            throw new RuntimeException('The protected Osceola report relay token is not configured.');
        }

        $headers = ['User-Agent' => 'VVR Command Center/1.0'];
        if ($usingRelay) $headers['X-VVR-Relay-Token'] = $relayToken;

        try {
            $response = Http::accept('application/pdf')->withHeaders($headers)
                ->timeout((int) config('surplus_research.osceola.timeout', 30))
                ->retry((int) config('surplus_research.osceola.retries', 2), 500, null, false)
                ->get($downloadUrl);
        } catch (ConnectionException $exception) {
            throw new RuntimeException($usingRelay
                ? 'The protected Osceola report relay could not be reached.'
                : 'The Osceola Clerk report could not be downloaded.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('The Osceola Clerk report returned HTTP '.$response->status().'.');
        }

        $contents = $response->body();
        $size = strlen($contents);
        if ($size < 100 || $size > (int) config('surplus_research.osceola.max_file_bytes', 15728640)) {
            throw new RuntimeException('The Osceola Clerk report file size is outside the allowed range.');
        }
        if (! str_starts_with($contents, '%PDF-')) {
            throw new RuntimeException('The Osceola Clerk endpoint did not return a PDF document.');
        }

        // Traceability always points to the government source, never the transport relay.
        return new DownloadedCountyReport($contents, $sourceUrl, hash('sha256', $contents), $size);
    }
}
