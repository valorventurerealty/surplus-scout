@csrf
@if(isset($event)) @method('PUT') @endif
@php
    $value = fn (string $key, mixed $fallback = null) => old($key, isset($event) ? data_get($event, $key) : data_get($defaults ?? [], $key, $fallback));
    $selectedEventType = $value('event_type');
    $selectedEventType = $selectedEventType instanceof \App\Enums\AuctionEventType ? $selectedEventType->value : $selectedEventType;
@endphp
<div
    x-data="{ eventType: @js($selectedEventType) }"
    class="grid gap-5 sm:grid-cols-2"
>
    <div class="sm:col-span-2">
        <label for="property_id" class="text-sm font-medium">Linked VVR property</label>
        <select id="property_id" name="property_id" class="form-input">
            <option value="">No linked property</option>
            @foreach($properties as $property)
                <option value="{{ $property->id }}" @selected((string) $value('property_id') === (string) $property->id)>{{ $property->address }}, {{ $property->city }}, {{ $property->state }} · {{ $property->parcel_id ?: 'No parcel' }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-500">Optional. Link the event to an existing CRM property when applicable.</p>
        <x-form-error name="property_id" />
    </div>

    <div>
        <label for="event_type" class="text-sm font-medium">Event type *</label>
        <select id="event_type" name="event_type" required x-model="eventType" class="form-input">
            <option value="">Choose event type</option>
            @foreach($eventTypes as $type)
                <option value="{{ $type->value }}" @selected($selectedEventType === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <x-form-error name="event_type" />
    </div>

    <div>
        <label for="title" class="text-sm font-medium">Event title <span x-show="eventType === 'meeting'">*</span></label>
        <input id="title" name="title" maxlength="255" x-bind:required="eventType === 'meeting'" value="{{ $value('title') }}" placeholder="Meeting with seller" class="form-input">
        <p class="mt-1 text-xs text-slate-500">Required for meetings. Auction titles are generated automatically when left blank.</p>
        <x-form-error name="title" />
    </div>

    <div>
        <label for="date" class="text-sm font-medium">Date *</label>
        <input id="date" name="date" type="date" required value="{{ old('date', isset($event) ? $event->starts_at->format('Y-m-d') : data_get($defaults ?? [], 'date')) }}" class="form-input">
        <x-form-error name="date" />
    </div>

    <div>
        <label for="time" class="text-sm font-medium">Time *</label>
        <input id="time" name="time" type="time" required value="{{ old('time', isset($event) ? $event->starts_at->format('H:i') : data_get($defaults ?? [], 'time', '09:00')) }}" class="form-input">
        <p class="mt-1 text-xs text-slate-500">Displayed in {{ config('app.timezone') }}.</p>
        <x-form-error name="time" />
    </div>

    <div>
        <label for="parcel_number" class="text-sm font-medium">Parcel number <span x-show="eventType !== 'meeting'">*</span></label>
        <input id="parcel_number" name="parcel_number" maxlength="120" x-bind:required="eventType !== 'meeting'" value="{{ $value('parcel_number') }}" placeholder="31-12-27-7227-0011-0120" class="form-input">
        <x-form-error name="parcel_number" />
    </div>

    <div>
        <label for="county" class="text-sm font-medium">County <span x-show="eventType !== 'meeting'">*</span></label>
        <select id="county" name="county" x-bind:required="eventType !== 'meeting'" class="form-input">
            <option value="">Choose county</option>
            @foreach($counties as $county)
                <option value="{{ $county->value }}" @selected($value('county') instanceof \App\Enums\AuctionCounty ? $value('county')->value === $county->value : $value('county') === $county->value)>{{ $county->label() }}</option>
            @endforeach
        </select>
        <x-form-error name="county" />
    </div>

    <div class="sm:col-span-2">
        <label for="property_address" class="text-sm font-medium">Location or property address <span x-show="eventType !== 'meeting'">*</span></label>
        <input id="property_address" name="property_address" maxlength="255" x-bind:required="eventType !== 'meeting'" value="{{ $value('property_address') }}" placeholder="120 Bayberry Rd or meeting location" class="form-input">
        <x-form-error name="property_address" />
    </div>

    <div class="sm:col-span-2">
        <label for="auction_url" class="text-sm font-medium">Event link <span x-show="eventType !== 'meeting'">*</span></label>
        <input id="auction_url" name="auction_url" type="url" inputmode="url" maxlength="2048" x-bind:required="eventType !== 'meeting'" value="{{ $value('auction_url') }}" placeholder="https://..." class="form-input">
        <p class="mt-1 text-xs text-slate-500">Required for auctions. Meetings may use this for a video or scheduling link.</p>
        <x-form-error name="auction_url" />
    </div>

    @if(auth()->user()->canViewPropertyFinancials())
        <div x-show="eventType !== 'meeting'" x-cloak>
            <label for="max_bid" class="text-sm font-medium">Max bid</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-3 top-2.5 text-slate-500">$</span>
                <input id="max_bid" name="max_bid" type="number" min="0" step="0.01" value="{{ $value('max_bid') }}" class="form-input pl-7">
            </div>
            <x-form-error name="max_bid" />
        </div>
    @endif

    <div @class(['sm:col-span-2' => ! auth()->user()->canViewPropertyFinancials()])>
        <label for="notes" class="text-sm font-medium">Notes</label>
        <textarea id="notes" name="notes" rows="5" maxlength="10000" placeholder="Agenda, research notes, title issue, or other event details" class="form-input">{{ $value('notes') }}</textarea>
        <x-form-error name="notes" />
    </div>
</div>
<div class="mt-8 flex justify-end gap-3">
    <a href="{{ isset($event) ? route('calendar.show', $event) : route('calendar.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Cancel</a>
    <button class="rounded-lg bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-300">{{ isset($event) ? 'Save event' : 'Add to calendar' }}</button>
</div>
