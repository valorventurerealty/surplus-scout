<?php

namespace App\Services;

use App\Models\CalendarEvent;

class GoogleCalendarEventMapper
{
    /** @return array<string, mixed> */
    public function map(CalendarEvent $event): array
    {
        $duration = max(15, (int) config('services.google_calendar.default_duration_minutes', 60));
        $timezone = (string) config('app.timezone');
        $description = collect([
            $event->event_type->isAuction() ? 'VVR Command Center auction event' : 'VVR Command Center meeting',
            $event->parcel_number ? 'Parcel: '.$event->parcel_number : null,
            $event->county ? 'County: '.$event->county->label().' County' : null,
            $event->auction_url ? 'Event link: '.$event->auction_url : null,
            'VVR record: '.route('calendar.show', $event),
            $event->notes ? "Notes:\n".$event->notes : null,
        ])->filter()->implode("\n\n");

        $payload = [
            'id' => $this->eventId($event),
            'summary' => $event->displayTitle(),
            'description' => $description,
            'start' => [
                'dateTime' => $event->starts_at->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => ($event->ends_at ?: $event->starts_at->copy()->addMinutes($duration))->toIso8601String(),
                'timeZone' => $timezone,
            ],
            'extendedProperties' => [
                'private' => [
                    'vvr_calendar_event_id' => (string) $event->id,
                    ...($event->normalized_parcel_number ? ['vvr_normalized_parcel' => $event->normalized_parcel_number] : []),
                ],
            ],
        ];

        if ($event->property_address) {
            $payload['location'] = $event->property_address;
        }

        return $payload;
    }

    public function eventId(CalendarEvent $event): string
    {
        return 'vvr'.hash('sha256', (string) config('app.url').'|calendar-event|'.$event->id);
    }
}
