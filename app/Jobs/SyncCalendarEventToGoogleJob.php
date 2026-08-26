<?php

namespace App\Jobs;

use App\Contracts\GoogleCalendarGatewayInterface;
use App\Enums\GoogleCalendarSyncStatus;
use App\Models\CalendarEvent;
use App\Services\GoogleCalendarEventMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SyncCalendarEventToGoogleJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 45;
    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $calendarEventId, public int $syncVersion) {}

    public function uniqueId(): string
    {
        return $this->calendarEventId.':'.$this->syncVersion;
    }

    public function handle(GoogleCalendarGatewayInterface $gateway, GoogleCalendarEventMapper $mapper): void
    {
        $event = CalendarEvent::withTrashed()->with('googleCalendarConnection')->find($this->calendarEventId);
        if (! $event || $event->google_sync_version !== $this->syncVersion) {
            return;
        }
        $connection = $event->googleCalendarConnection;
        if (! $connection || ! $connection->is_active || blank($connection->refresh_token)) {
            $this->currentEventQuery()->update([
                'google_sync_status' => GoogleCalendarSyncStatus::Failed->value,
                'google_sync_error' => 'Google Calendar is disconnected. Reconnect it and retry this event.',
                'google_sync_attempted_at' => now(),
            ]);

            return;
        }

        $this->currentEventQuery()->update(['google_sync_attempted_at' => now(), 'google_sync_error' => null]);
        $calendarId = (string) ($event->google_calendar_id ?: $connection->calendar_id);

        if ($event->trashed()) {
            if (filled($event->google_event_id)) {
                $gateway->deleteEvent($connection, $calendarId, $event->google_event_id);
            }
            $this->currentEventQuery()->update([
                'google_sync_status' => GoogleCalendarSyncStatus::Deleted->value,
                'google_sync_error' => null,
                'google_synced_at' => now(),
            ]);
            $connection->updateQuietly(['last_synced_at' => now(), 'last_error' => null]);

            return;
        }

        $payload = $mapper->map($event);
        $result = filled($event->google_event_id)
            ? $gateway->updateEvent($connection, $calendarId, $event->google_event_id, $payload)
            : $gateway->createEvent($connection, $calendarId, $payload);

        $this->currentEventQuery()->update([
            'google_event_id' => (string) ($result['id'] ?? $payload['id']),
            'google_event_html_link' => $result['htmlLink'] ?? $event->google_event_html_link,
            'google_event_etag' => $result['etag'] ?? null,
            'google_sync_status' => GoogleCalendarSyncStatus::Synced->value,
            'google_sync_error' => null,
            'google_synced_at' => now(),
        ]);
        $connection->updateQuietly(['last_synced_at' => now(), 'last_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception ? Str::limit($exception->getMessage(), 1000) : 'Google Calendar synchronization failed.';
        $event = CalendarEvent::withTrashed()->whereKey($this->calendarEventId)->where('google_sync_version', $this->syncVersion)->first();
        $event?->updateQuietly([
            'google_sync_status' => GoogleCalendarSyncStatus::Failed->value,
            'google_sync_error' => $message,
            'google_sync_attempted_at' => now(),
        ]);
        $event?->googleCalendarConnection?->updateQuietly(['last_error' => $message]);
    }

    private function currentEventQuery(): Builder
    {
        return CalendarEvent::withTrashed()
            ->whereKey($this->calendarEventId)
            ->where('google_sync_version', $this->syncVersion);
    }
}
