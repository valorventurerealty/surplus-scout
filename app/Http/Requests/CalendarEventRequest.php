<?php

namespace App\Http\Requests;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\AuctionCounty;
use App\Enums\AuctionEventType;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class CalendarEventRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [
            'title' => filled($this->input('title')) ? trim((string) $this->input('title')) : null,
            'parcel_number' => filled($this->input('parcel_number')) ? trim((string) $this->input('parcel_number')) : null,
            'auction_url' => filled($this->input('auction_url')) ? trim((string) $this->input('auction_url')) : null,
            'property_address' => filled($this->input('property_address')) ? trim((string) $this->input('property_address')) : null,
            'county' => filled($this->input('county')) ? $this->input('county') : null,
            'property_id' => filled($this->input('property_id')) ? $this->input('property_id') : null,
        ];

        if ($this->exists('max_bid')) {
            $data['max_bid'] = filled($this->input('max_bid')) ? $this->input('max_bid') : null;
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $financialRule = Rule::prohibitedIf(! $this->user()?->canViewPropertyFinancials());
        $auctionRequired = Rule::requiredIf(fn (): bool => AuctionEventType::tryFrom((string) $this->input('event_type'))?->isAuction() === true);
        $meetingRequired = Rule::requiredIf(fn (): bool => $this->input('event_type') === AuctionEventType::Meeting->value);

        return [
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'title' => [$meetingRequired, 'nullable', 'string', 'max:255'],
            'parcel_number' => [$auctionRequired, 'nullable', 'string', 'max:120'],
            'event_type' => ['required', Rule::enum(AuctionEventType::class)],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'auction_url' => [$auctionRequired, 'nullable', 'string', 'max:2048', 'url:https'],
            'property_address' => [$auctionRequired, 'nullable', 'string', 'max:255'],
            'county' => [$auctionRequired, 'nullable', Rule::enum(AuctionCounty::class)],
            'max_bid' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['parcel_number', 'event_type', 'date', 'time'])) {
                return;
            }

            $eventType = AuctionEventType::tryFrom($this->string('event_type')->toString());
            if (! $eventType?->isAuction() || ! filled($this->input('parcel_number'))) {
                return;
            }

            $startsAt = CarbonImmutable::createFromFormat(
                '!Y-m-d H:i',
                $this->string('date')->toString().' '.$this->string('time')->toString(),
                config('app.timezone'),
            );
            $normalizedParcel = app(PropertyNormalizer::class)->parcelId($this->string('parcel_number')->toString());
            $query = CalendarEvent::query()
                ->where('normalized_parcel_number', $normalizedParcel)
                ->where('event_type', $this->string('event_type')->toString())
                ->where('starts_at', $startsAt);

            if ($event = $this->route('event')) {
                $query->whereKeyNot($event->id);
            }

            if ($query->exists()) {
                $validator->errors()->add('date', 'This parcel already has the same auction type scheduled at that date and time.');
            }
        }];
    }
}
