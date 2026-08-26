<?php

namespace App\Jobs;

use App\Models\GoogleCalendarConnection;
use App\Services\GoogleCalendarInboundSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ImportGoogleCalendarEventsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 75;
    public int $uniqueFor = 240;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function uniqueId(): string
    {
        return 'active-google-calendar-inbound-sync';
    }

    public function handle(GoogleCalendarInboundSyncService $sync): void
    {
        $connection = GoogleCalendarConnection::active();
        if (! $connection?->inbound_sync_enabled) {
            return;
        }

        $sync->import($connection);
    }

    public function failed(?Throwable $exception): void
    {
        GoogleCalendarConnection::active()?->updateQuietly([
            'inbound_sync_error' => $exception
                ? Str::limit($exception->getMessage(), 1000)
                : 'Google Calendar booking import failed.',
        ]);
    }
}
