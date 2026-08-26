<?php

namespace App\Services;

use App\Contracts\GoogleCalendarGatewayInterface;
use App\Enums\AuctionEventType;
use App\Enums\CalendarEventSource;
use App\Enums\GoogleCalendarSyncStatus;
use App\Exceptions\GoogleCalendarException;
use App\Exceptions\GoogleCalendarSyncTokenExpiredException;
use App\Models\CalendarEvent;
use App\Models\GoogleCalendarConnection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

class GoogleCalendarInboundSyncService
{
    public function __construct(private readonly GoogleCalendarGatewayInterface $gateway) {}

    /** @return array{created: int, updated: int, cancelled: int, unchanged: int, skipped: int} */
    public function import(GoogleCalendarConnection $connection): array
    {
        if (! $connection->is_active || ! $connection->inbound_sync_enabled || blank($connection->calendar_id)) {
            return $this->emptySummary();
        }

        try {
            return $this->importPages($connection, $connection->inbound_sync_token);
        } catch (GoogleCalendarSyncTokenExpiredException) {
            if (blank($connection->inbound_sync_token)) {
                throw new GoogleCalendarException('Google Calendar could not establish a fresh inbound synchronization checkpoint.');
            }

            $connection->updateQuietly(['inbound_sync_token' => null]);

            return $this->importPages($connection->refresh(), null);
        }
    }

    /** @return array{created: int, updated: int, cancelled: int, unchanged: int, skipped: int} */
    private function importPages(GoogleCalendarConnection $connection, ?string $syncToken): array
    {
        $summary = $this->emptySummary();
        $pageToken = null;
        $nextSyncToken = null;
        $pageCount = 0;
        $maxPages = max(1, min(100, (int) config('services.google_calendar.inbound_max_pages', 10)));
        $timeMin = $connection->inbound_sync_started_at?->toIso8601String() ?: now()->toIso8601String();

        do {
            if (++$pageCount > $maxPages) {
                throw new GoogleCalendarException('Google Calendar returned more booking pages than the configured safety limit. Run the import again after increasing the bounded page limit.');
            }

            $page = $this->gateway->listEvents(
                $connection,
                (string) $connection->calendar_id,
                $syncToken,
                $syncToken ? null : $timeMin,
                $pageToken,
            );

            foreach ($page['items'] as $googleEvent) {
                $outcome = $this->reconcile($connection, $googleEvent);
                $summary[$outcome]++;
            }

            $pageToken = $page['next_page_token'];
            $nextSyncToken = $page['next_sync_token'] ?: $nextSyncToken;
        } while ($pageToken);

        if (blank($nextSyncToken)) {
            throw new GoogleCalendarException('Google Calendar did not return a synchronization checkpoint. No checkpoint was advanced.');
        }

        $connection->updateQuietly([
            'inbound_sync_token' => $nextSyncToken,
            'last_inbound_sync_at' => now(),
            'inbound_sync_error' => null,
        ]);

        return $summary;
    }

    private function reconcile(GoogleCalendarConnection $connection, array $googleEvent): string
    {
        $googleEventId = trim((string) ($googleEvent['id'] ?? ''));
        if ($googleEventId === '' || strlen($googleEventId) > 1024) {
            return 'skipped';
        }

        $calendarId = (string) $connection->calendar_id;
        $eventKey = $this->eventKey($connection, $calendarId, $googleEventId);
        $existing = CalendarEvent::withTrashed()->where('google_event_key', $eventKey)->first()
            ?? CalendarEvent::withTrashed()
                ->where('google_calendar_connection_id', $connection->id)
                ->where('google_calendar_id', $calendarId)
                ->where('google_event_id', $googleEventId)
                ->first();

        if ($existing && ! $existing->isGoogleManaged()) {
            return 'skipped';
        }

        $vvrEventId = data_get($googleEvent, 'extendedProperties.private.vvr_calendar_event_id');
        if (filled($vvrEventId) || str_starts_with($googleEventId, 'vvr')) {
            return 'skipped';
        }

        if (($googleEvent['status'] ?? null) === 'cancelled') {
            if (! $existing || ! $existing->isGoogleManaged()) {
                return 'unchanged';
            }

            if ($existing->trashed()) {
                return 'unchanged';
            }

            $existing->update([
                'google_event_etag' => $googleEvent['etag'] ?? $existing->google_event_etag,
                'google_cancelled_at' => now(),
                'google_sync_status' => GoogleCalendarSyncStatus::Deleted,
                'google_synced_at' => now(),
                'updated_by' => $connection->user_id,
            ]);
            $existing->delete();

            return 'cancelled';
        }

        if (($googleEvent['eventType'] ?? 'default') !== 'default') {
            return 'skipped';
        }

        $startsAt = $this->dateTime(data_get($googleEvent, 'start.dateTime'), data_get($googleEvent, 'start.date'));
        if (! $startsAt) {
            return 'skipped';
        }
        $endsAt = $this->dateTime(data_get($googleEvent, 'end.dateTime'), data_get($googleEvent, 'end.date'));
        $etag = filled($googleEvent['etag'] ?? null) ? (string) $googleEvent['etag'] : null;
        if ($existing && ! $existing->trashed() && $etag && hash_equals((string) $existing->google_event_etag, $etag)) {
            return 'unchanged';
        }

        $attendees = collect($googleEvent['attendees'] ?? [])
            ->filter(fn (mixed $attendee): bool => is_array($attendee))
            ->take(100)
            ->map(fn (array $attendee): array => array_filter([
                'name' => filled($attendee['displayName'] ?? null) ? Str::limit((string) $attendee['displayName'], 255, '') : null,
                'email' => filled($attendee['email'] ?? null) ? Str::lower(Str::limit((string) $attendee['email'], 255, '')) : null,
                'response_status' => filled($attendee['responseStatus'] ?? null) ? Str::limit((string) $attendee['responseStatus'], 40, '') : null,
                'organizer' => (bool) ($attendee['organizer'] ?? false),
                'self' => (bool) ($attendee['self'] ?? false),
            ], fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();

        $data = [
            'title' => filled($googleEvent['summary'] ?? null) ? Str::limit(trim((string) $googleEvent['summary']), 255, '') : null,
            'event_type' => AuctionEventType::Meeting,
            'source' => CalendarEventSource::Google,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'auction_url' => $this->meetingUrl($googleEvent),
            'property_address' => filled($googleEvent['location'] ?? null) ? Str::limit(trim((string) $googleEvent['location']), 255, '') : null,
            'parcel_number' => null,
            'normalized_parcel_number' => null,
            'county' => null,
            'max_bid' => null,
            'notes' => filled($googleEvent['description'] ?? null) ? Str::limit((string) $googleEvent['description'], 10000, '') : null,
            'google_calendar_connection_id' => $connection->id,
            'google_calendar_id' => $calendarId,
            'google_event_id' => $googleEventId,
            'google_event_key' => $eventKey,
            'google_event_html_link' => $this->httpsUrl($googleEvent['htmlLink'] ?? null),
            'google_event_etag' => $etag,
            'google_attendees' => $attendees ?: null,
            'google_organizer_email' => filled(data_get($googleEvent, 'organizer.email'))
                ? Str::lower(Str::limit((string) data_get($googleEvent, 'organizer.email'), 255, ''))
                : null,
            'google_updated_at' => $this->dateTime($googleEvent['updated'] ?? null),
            'google_cancelled_at' => null,
            'google_sync_status' => GoogleCalendarSyncStatus::Synced,
            'google_sync_error' => null,
            'google_sync_attempted_at' => now(),
            'google_synced_at' => now(),
            'updated_by' => $connection->user_id,
        ];

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update($data);

            return 'updated';
        }

        CalendarEvent::query()->create([...$data, 'created_by' => $connection->user_id]);

        return 'created';
    }

    private function eventKey(GoogleCalendarConnection $connection, string $calendarId, string $googleEventId): string
    {
        return hash('sha256', $connection->id.'|'.$calendarId.'|'.$googleEventId);
    }

    private function dateTime(mixed $dateTime, mixed $date = null): ?CarbonImmutable
    {
        try {
            if (filled($dateTime)) {
                return CarbonImmutable::parse((string) $dateTime)->setTimezone(config('app.timezone'));
            }
            if (filled($date)) {
                return CarbonImmutable::parse((string) $date, config('app.timezone'))->startOfDay();
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function meetingUrl(array $googleEvent): ?string
    {
        $candidates = collect([
            $googleEvent['hangoutLink'] ?? null,
            ...collect(data_get($googleEvent, 'conferenceData.entryPoints', []))
                ->where('entryPointType', 'video')
                ->pluck('uri')
                ->all(),
        ]);

        return $candidates->map(fn (mixed $url): ?string => $this->httpsUrl($url))->filter()->first();
    }

    private function httpsUrl(mixed $url): ?string
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return null;
        }

        return Str::limit($url, 2048, '');
    }

    /** @return array{created: int, updated: int, cancelled: int, unchanged: int, skipped: int} */
    private function emptySummary(): array
    {
        return ['created' => 0, 'updated' => 0, 'cancelled' => 0, 'unchanged' => 0, 'skipped' => 0];
    }
}
