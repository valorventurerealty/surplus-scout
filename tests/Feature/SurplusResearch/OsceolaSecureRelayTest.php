<?php

namespace Tests\Feature\SurplusResearch;

use App\Services\SurplusResearch\Osceola\OsceolaClerkSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OsceolaSecureRelayTest extends TestCase
{
    public function test_protected_relay_is_used_for_transport_while_clerk_remains_the_source(): void
    {
        config()->set('surplus_research.osceola.download_url', 'https://vvr-osceola-relay.example.workers.dev');
        config()->set('surplus_research.osceola.relay_token', str_repeat('a', 64));
        $pdf = '%PDF-'.str_repeat('x', 200);
        Http::fake(function (Request $request) use ($pdf) {
            $this->assertSame('https://vvr-osceola-relay.example.workers.dev', $request->url());
            $this->assertSame([str_repeat('a', 64)], $request->header('X-VVR-Relay-Token'));
            return Http::response($pdf, 200, ['Content-Type' => 'application/pdf']);
        });

        $download = (new OsceolaClerkSource)->download();

        $this->assertSame(config('surplus_research.osceola.source_url'), $download->sourceUrl);
        $this->assertSame(hash('sha256', $pdf), $download->sha256);
    }

    public function test_relay_requires_a_strong_private_token(): void
    {
        config()->set('surplus_research.osceola.download_url', 'https://vvr-osceola-relay.example.workers.dev');
        config()->set('surplus_research.osceola.relay_token', 'short');
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('relay token is not configured');
        (new OsceolaClerkSource)->download();
    }

    public function test_non_https_relay_is_rejected(): void
    {
        config()->set('surplus_research.osceola.download_url', 'http://relay.example.test');
        config()->set('surplus_research.osceola.relay_token', str_repeat('a', 64));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('valid HTTPS URL');
        (new OsceolaClerkSource)->download();
    }
}
