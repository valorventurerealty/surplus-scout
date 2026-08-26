<?php

namespace App\Services;

use App\Domain\Properties\PropertyNormalizer;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CalendarEventService
{
    public function __construct(
        private readonly PropertyNormalizer $normalizer,
        private readonly GoogleCalendarSyncService $googleCalendarSync,
    ) {}

    public function create(array $data, User $actor): CalendarEvent
    {
        $event = DB::transaction(fn (): CalendarEvent => CalendarEvent::query()->create([
            ...$this->prepare($data),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]));
        $this->googleCalendarSync->queue($event);

        return $event->refresh();
    }

    public function update(CalendarEvent $event, array $data, User $actor): CalendarEvent
    {
        $event = DB::transaction(function () use ($event, $data, $actor): CalendarEvent {
            $event->update([...$this->prepare($data), 'updated_by' => $actor->id]);

            return $event->refresh();
        });
        $this->googleCalendarSync->queue($event);

        return $event->refresh();
    }

    public function delete(CalendarEvent $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $event->updateQuietly(['updated_by' => $actor->id]);
            $event->delete();
        });
        $this->googleCalendarSync->queue(CalendarEvent::withTrashed()->findOrFail($event->id), true);
    }

    private function prepare(array $data): array
    {
        $data['normalized_parcel_number'] = filled($data['parcel_number'] ?? null)
            ? $this->normalizer->parcelId($data['parcel_number'])
            : null;
        $data['starts_at'] = CarbonImmutable::createFromFormat(
            '!Y-m-d H:i',
            $data['date'].' '.$data['time'],
            config('app.timezone'),
        );

        return Arr::except($data, ['date', 'time']);
    }
}
