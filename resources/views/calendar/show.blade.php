<x-layouts.app title="{{ $event->displayTitle() }}" heading="Calendar event" subheading="{{ $event->event_type->label() }}">
    @php
        $identifiers = collect([
            $event->county ? $event->county->label().' County' : null,
            $event->parcel_number ? 'Parcel '.$event->parcel_number : null,
            $event->property_address,
        ])->filter()->unique();
        $details = collect([
            ['Event type', $event->event_type->label()],
            ['Date', $event->starts_at->format('F j, Y')],
            ['Time', $event->starts_at->format('g:i A T')],
            $event->ends_at ? ['Ends', $event->ends_at->format('F j, Y g:i A T')] : null,
            ['Source', $event->source->label()],
            $event->county ? ['County', $event->county->label().' County'] : null,
            $event->parcel_number ? ['Parcel number', $event->parcel_number] : null,
            $event->property_address ? ['Location / property', $event->property_address] : null,
        ])->filter();
    @endphp

    @if($event->isGoogleManaged())
        <div class="mb-6 rounded-xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200">
            <p class="font-semibold">Managed by Google Calendar</p>
            <p class="mt-1">Reschedule or cancel this booking in Google Calendar. VVR will update it during the next inbound synchronization and will not send it back to Google.</p>
        </div>
    @endif

    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <h2 class="text-2xl font-bold tracking-tight">{{ $event->displayTitle() }}</h2>
            @if($identifiers->isNotEmpty())<p class="mt-2 text-sm text-slate-500">{{ $identifiers->implode(' · ') }}</p>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if($event->auction_url)<a href="{{ $event->auction_url }}" target="_blank" rel="noopener noreferrer" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950">Open event link ↗</a>@endif
            @can('update', $event)<a href="{{ route('calendar.edit', $event) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Edit</a>@endcan
            @can('delete', $event)
                <form method="POST" action="{{ route('calendar.destroy', $event) }}" onsubmit="return confirm('Archive this calendar event?')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg border border-rose-300 px-4 py-2 text-sm text-rose-700 dark:border-rose-900 dark:text-rose-400">Archive</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="font-semibold">Event information</h3>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                @foreach($details as [$label, $value])
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mt-1 text-sm font-medium">{{ $value }}</dd></div>
                @endforeach
                @can('viewFinancials', $event)
                    @if($event->max_bid !== null)<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Max bid</dt><dd class="mt-1 text-sm font-medium">${{ number_format((float) $event->max_bid, 2) }}</dd></div>@endif
                @endcan
            </dl>
            @if($event->notes)<div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Notes</p><p class="mt-2 whitespace-pre-line text-sm leading-6">{{ $event->notes }}</p></div>@endif
            @if($event->google_attendees)
                <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Attendees</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach($event->google_attendees as $attendee)
                            <li><span class="font-medium">{{ $attendee['name'] ?? $attendee['email'] ?? 'Google attendee' }}</span>@if(filled($attendee['name'] ?? null) && filled($attendee['email'] ?? null)) <span class="text-slate-500">· {{ $attendee['email'] }}</span>@endif @if(filled($attendee['response_status'] ?? null))<span class="text-xs text-slate-500">({{ str($attendee['response_status'])->headline() }})</span>@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="font-semibold">Linked property</h3>
                @if($event->property)
                    <a href="{{ route('properties.show', $event->property) }}" class="mt-4 block font-semibold text-amber-700 hover:underline dark:text-amber-400">{{ $event->property->full_address }}</a>
                    <p class="mt-2 text-xs text-slate-500">Open the CRM property record.</p>
                @else
                    <p class="mt-4 text-sm text-slate-500">This event is not linked to a VVR property.</p>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="font-semibold">Google Calendar</h3>
                <p class="mt-3 text-sm font-medium">{{ $event->google_sync_status->label() }}</p>
                @if($event->google_synced_at)<p class="mt-1 text-xs text-slate-500">Last synced {{ $event->google_synced_at->format('M j, Y g:i A T') }}</p>@endif
                @if($event->google_sync_error)<p class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ $event->google_sync_error }}</p>@endif
                <div class="mt-4 flex flex-wrap gap-2">
                    @if($event->google_event_html_link)<a href="{{ $event->google_event_html_link }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold dark:border-slate-700">Open Google event ↗</a>@endif
                    @can('update', $event)
                        <form method="POST" action="{{ route('calendar.google-sync', $event, false) }}">
                            @csrf
                            <button class="rounded-lg border border-amber-400 px-3 py-2 text-xs font-semibold text-amber-800 dark:text-amber-300">{{ $event->google_sync_status === \App\Enums\GoogleCalendarSyncStatus::Synced ? 'Sync again' : 'Sync to Google' }}</button>
                        </form>
                    @endcan
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
