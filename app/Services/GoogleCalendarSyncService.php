<?php

namespace App\Services;

use App\Enums\GoogleCalendarSyncStatus;
use App\Enums\CalendarEventSource;
use App\Jobs\SyncCalendarEventToGoogleJob;
use App\Models\CalendarEvent;
use App\Models\GoogleCalendarConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleCalendarSyncService
{
    public function queue(CalendarEvent $event, bool $deletion = false): bool
    {
        if ($event->source === CalendarEventSource::Google) {
            return false;
        }

        $connection = GoogleCalendarConnection::active();
        if (! $connection) {
            if (! $event->trashed()) {
                $event->updateQuietly([
                    'google_sync_status' => GoogleCalendarSyncStatus::NotConfigured,
                    'google_sync_error' => null,
                ]);
            }

            return false;
        }

        try {
            [$eventId, $version] = DB::transaction(function () use ($event, $connection, $deletion): array {
                $locked = CalendarEvent::withTrashed()->lockForUpdate()->findOrFail($event->id);
                $version = $locked->google_sync_version + 1;
                $locked->updateQuietly([
                    'google_calendar_connection_id' => $connection->id,
                    'google_calendar_id' => $locked->google_calendar_id ?: $connection->calendar_id,
                    'google_sync_status' => $deletion
                        ? GoogleCalendarSyncStatus::DeletionPending
                        : GoogleCalendarSyncStatus::Pending,
                    'google_sync_version' => $version,
                    'google_sync_error' => null,
                ]);

                return [$locked->id, $version];
            });

            SyncCalendarEventToGoogleJob::dispatch($eventId, $version)->afterCommit();

            return true;
        } catch (Throwable $exception) {
            Log::error('Unable to queue Google Calendar synchronization.', [
                'calendar_event_id' => $event->id,
                'exception' => $exception::class,
            ]);
            CalendarEvent::withTrashed()->whereKey($event->id)->update([
                'google_sync_status' => GoogleCalendarSyncStatus::Failed->value,
                'google_sync_error' => 'The synchronization job could not be queued.',
            ]);

            return false;
        }
    }
}
