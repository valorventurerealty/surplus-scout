<x-layouts.app title="Google Calendar" heading="Google Calendar" subheading="Synchronize VVR events and import Google bookings">
    <div class="mx-auto max-w-5xl space-y-6">
        @if($errors->any())
            <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"><p class="font-semibold">Google Calendar needs attention.</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <h2 class="text-lg font-semibold">Connection</h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">VVR-created events sync to Google. When booking import is enabled, future Google-created events are mirrored into VVR as Google-managed meetings.</p>
                </div>
                @if($connection)
                    <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Connected</span>
                @else
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Not connected</span>
                @endif
            </div>

            @if(! $configured)
                <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    Add <code>GOOGLE_CALENDAR_CLIENT_ID</code>, <code>GOOGLE_CALENDAR_CLIENT_SECRET</code>, and the approved redirect URI to the private production <code>.env</code> file before connecting.
                </div>
            @elseif(! $connection)
                <a href="{{ route('google-calendar.connect', [], false) }}" class="mt-5 inline-flex rounded-lg bg-amber-400 px-5 py-2.5 text-sm font-semibold text-slate-950 hover:bg-amber-300">Connect Google Calendar</a>
            @endif

            @if($connection)
                <dl class="mt-6 grid gap-5 border-t border-slate-200 pt-5 sm:grid-cols-2 dark:border-slate-800">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Google account</dt><dd class="mt-1 text-sm font-medium">{{ $connection->google_account_email ?: 'Authorized Google account' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Connected by</dt><dd class="mt-1 text-sm font-medium">{{ $connection->user?->name ?: 'VVR administrator' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Destination</dt><dd class="mt-1 text-sm font-medium">{{ $connection->calendar_name ?: $connection->calendar_id }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last outbound sync</dt><dd class="mt-1 text-sm font-medium">{{ $connection->last_synced_at?->format('M j, Y g:i A T') ?: 'No VVR events synced yet' }}</dd></div>
                </dl>
                @if($connection->last_error)
                    <div class="mt-5 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ $connection->last_error }}</div>
                @endif
            @endif
        </section>

        @if($connection)
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold">Destination calendar</h2>
                <p class="mt-1 text-sm text-slate-500">Only calendars where the connected account has write access are available.</p>
                @if($calendars)
                    <form method="POST" action="{{ route('google-calendar.update', [], false) }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                        @csrf @method('PUT')
                        <label class="flex-1 text-sm font-medium">Google Calendar
                            <select name="calendar_id" required class="form-input mt-2">
                                @foreach($calendars as $calendar)
                                    <option value="{{ $calendar['id'] }}" @selected($connection->calendar_id === $calendar['id'])>{{ $calendar['name'] }}{{ $calendar['primary'] ? ' (Primary)' : '' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white dark:bg-amber-400 dark:text-slate-950">Save destination</button>
                    </form>
                @else
                    <p class="mt-5 text-sm text-rose-600">The authorized calendar list is currently unavailable. Reconnect Google Calendar if this continues.</p>
                @endif
            </section>

            <section class="rounded-2xl border border-sky-200 bg-white p-6 shadow-sm dark:border-sky-950 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <h2 class="text-lg font-semibold">Google booking import</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">Imports future standard events from the selected Google Calendar as Meetings. Import begins when enabled; historical events are not pulled in. VVR-created Google events are excluded automatically.</p>
                    </div>
                    <span @class(['inline-flex self-start rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' => $connection->inbound_sync_enabled, 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! $connection->inbound_sync_enabled])>{{ $connection->inbound_sync_enabled ? 'Enabled' : 'Disabled' }}</span>
                </div>
                <dl class="mt-5 grid gap-4 border-t border-slate-200 pt-5 sm:grid-cols-3 dark:border-slate-800">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Import begins</dt><dd class="mt-1 text-sm font-medium">{{ $connection->inbound_sync_started_at?->format('M j, Y g:i A T') ?: 'Not enabled' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last inbound sync</dt><dd class="mt-1 text-sm font-medium">{{ $connection->last_inbound_sync_at?->format('M j, Y g:i A T') ?: 'Not run yet' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Imported records</dt><dd class="mt-1 text-sm font-medium">{{ number_format($importedCount) }} meeting(s)</dd></div>
                </dl>
                @if($connection->inbound_sync_error)<div class="mt-4 rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ $connection->inbound_sync_error }}</div>@endif
                <div class="mt-5 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('google-calendar.inbound-sync.update', [], false) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="enabled" value="{{ $connection->inbound_sync_enabled ? 0 : 1 }}">
                        <button class="rounded-lg border px-4 py-2 text-sm font-semibold {{ $connection->inbound_sync_enabled ? 'border-rose-300 text-rose-700 dark:border-rose-900 dark:text-rose-400' : 'border-sky-400 text-sky-800 dark:text-sky-300' }}">{{ $connection->inbound_sync_enabled ? 'Disable booking import' : 'Enable booking import' }}</button>
                    </form>
                    @if($connection->inbound_sync_enabled)
                        <form method="POST" action="{{ route('google-calendar.inbound-sync.run', [], false) }}">@csrf<button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500">Import now</button></form>
                    @endif
                </div>
                <p class="mt-4 text-xs leading-5 text-slate-500">Google is authoritative for imported meetings. Reschedule or cancel the booking in Google Calendar; VVR will reflect the change after the next five-minute scheduler run.</p>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold">Existing upcoming events</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $pendingCount }} upcoming event(s) are not yet synchronized or need retrying. Already-synced events are not duplicated.</p>
                    <form method="POST" action="{{ route('google-calendar.sync-upcoming', [], false) }}" class="mt-5">@csrf<button class="rounded-lg border border-amber-400 px-4 py-2 text-sm font-semibold text-amber-800 dark:text-amber-300">Sync upcoming events</button></form>
                </div>
                <div class="rounded-2xl border border-rose-200 bg-white p-6 shadow-sm dark:border-rose-950 dark:bg-slate-900">
                    <h2 class="font-semibold">Disconnect</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">This removes VVR's stored authorization. Existing events already created in Google Calendar remain there.</p>
                    <form method="POST" action="{{ route('google-calendar.disconnect', [], false) }}" class="mt-5" onsubmit="return confirm('Disconnect Google Calendar? Existing Google events will remain unchanged.')">@csrf @method('DELETE')<button class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 dark:border-rose-900 dark:text-rose-400">Disconnect Google Calendar</button></form>
                </div>
            </section>
        @endif

        <a href="{{ route('calendar.index') }}" class="inline-flex text-sm font-semibold text-amber-700 hover:underline dark:text-amber-400">← Back to calendar</a>
    </div>
</x-layouts.app>
