<x-layouts.app title="{{ $interaction->display_name }}" heading="{{ $interaction->event_type->label() }}: {{ $interaction->display_name }}" subheading="Received from Beside {{ $interaction->occurred_at->format('M j, Y g:i A') }}">
    <div class="mx-auto max-w-5xl space-y-6">
        <div><a href="{{ route('phone-interactions.index') }}" class="text-sm font-semibold text-amber-700 hover:underline dark:text-amber-400">← Back to Phone Calls</a></div>

        @if(!$interaction->contact)
            <section @class(['rounded-2xl border p-5', 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100' => $interaction->match_status === \App\Enums\PhoneInteractionMatchStatus::Unmatched, 'border-rose-300 bg-rose-50 text-rose-950 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-100' => $interaction->match_status === \App\Enums\PhoneInteractionMatchStatus::Conflicting])><h2 class="font-semibold">{{ $interaction->match_status->label() }} caller</h2><p class="mt-1 text-sm">{{ $interaction->match_status === \App\Enums\PhoneInteractionMatchStatus::Conflicting ? 'More than one contact has this phone number. Review the contacts before linking this activity.' : 'No existing contact matched this phone number. No contact was created automatically.' }}</p>@can('create', \App\Models\Contact::class)<a href="{{ route('contacts.create') }}" class="mt-3 inline-block text-sm font-semibold underline">Create contact after review</a>@endcan</section>
        @endif

        @can('update', $interaction)
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold">CRM contact link</h2><p class="mt-1 text-sm text-slate-500">Review the caller and select the correct existing contact. VVR will not create or merge contacts automatically.</p>
                <form method="POST" action="{{ route('phone-interactions.contact.update', $interaction) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <div class="flex-1">
                        <label for="contact_id" class="text-sm font-medium">Contact</label>
                        <select id="contact_id" name="contact_id" required class="form-input">
                            <option value="">Choose a contact</option>
                            @foreach($contacts as $contact)
                                <option value="{{ $contact->id }}" @selected((string) old('contact_id', $interaction->contact_id) === (string) $contact->id)>{{ $contact->full_name }}{{ $contact->company ? ' · '.$contact->company : '' }}{{ $contact->phone ? ' · '.$contact->phone : '' }}</option>
                            @endforeach
                        </select>
                        <x-form-error name="contact_id" />
                    </div>
                    <button type="submit" class="rounded-lg bg-amber-400 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-amber-300">Save contact link</button>
                </form>
            </section>
        @endcan

        <section class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-800 dark:bg-slate-900">
            @foreach([['Type',$interaction->event_type->label()],['Direction',$interaction->direction->label()],['Phone',$interaction->caller_phone],['Name',$interaction->caller_name],['Email',$interaction->caller_email],['Company',$interaction->caller_company],['Inbox',$interaction->inbox],['Duration',$interaction->duration_seconds !== null ? gmdate('i:s',$interaction->duration_seconds) : null],['Match',$interaction->match_status->label()]] as [$label,$value])<div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mt-1 text-sm">{{ $value ?: '—' }}</dd></div>@endforeach
            @if($interaction->contact)<div class="sm:col-span-2 lg:col-span-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Linked contact</dt><dd class="mt-1"><a href="{{ route('contacts.show',$interaction->contact) }}" class="text-sm font-semibold text-amber-700 hover:underline dark:text-amber-400">{{ $interaction->contact->full_name }}</a></dd></div>@endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold">Call summary</h2><div class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-700 dark:text-slate-300">{{ $interaction->summary ?: 'Beside did not provide a summary.' }}</div></section>

        @if($interaction->action_items)
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold">Action items</h2><ul class="mt-4 list-disc space-y-2 pl-5 text-sm">@foreach($interaction->action_items as $item)<li>{{ $item }}</li>@endforeach</ul></section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="flex items-center justify-between gap-3"><h2 class="font-semibold">Transcript</h2>@if($interaction->recording_url)<a href="{{ $interaction->recording_url }}" target="_blank" rel="noopener noreferrer" class="text-sm font-semibold text-amber-700 hover:underline dark:text-amber-400">Open recording ↗</a>@endif</div><div class="mt-4 whitespace-pre-wrap text-sm leading-7 text-slate-700 dark:text-slate-300">{{ $interaction->transcript ?: 'Beside did not provide a transcript.' }}</div></section>

        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/50"><p>Provider: Beside · Event ID: {{ $interaction->provider_event_id }}</p><p class="mt-1">Received by VVR: {{ $interaction->received_at->format('M j, Y g:i:s A') }}</p></section>
    </div>
</x-layouts.app>
