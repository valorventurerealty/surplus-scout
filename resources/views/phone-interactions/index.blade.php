<x-layouts.app title="Phone Calls" heading="Phone Calls" subheading="Beside calls, voicemails, messages, and captured leads">
    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([['Today', $metrics['today']], ['Inbound', $metrics['inbound']], ['Needs review', $metrics['unmatched']], ['All activity', $metrics['total']]] as [$label, $value])
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($value) }}</p></section>
        @endforeach
    </div>

    <form method="GET" class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_160px_150px_170px_auto]">
        <input name="search" value="{{ request('search') }}" placeholder="Search caller, phone, or summary" class="form-input mt-0">
        <select name="event_type" class="form-input mt-0"><option value="">All activity types</option>@foreach(\App\Enums\PhoneInteractionType::cases() as $type)<option value="{{ $type->value }}" @selected(request('event_type') === $type->value)>{{ $type->label() }}</option>@endforeach</select>
        <select name="direction" class="form-input mt-0"><option value="">All directions</option>@foreach(\App\Enums\PhoneInteractionDirection::cases() as $direction)<option value="{{ $direction->value }}" @selected(request('direction') === $direction->value)>{{ $direction->label() }}</option>@endforeach</select>
        <select name="match_status" class="form-input mt-0"><option value="">All match statuses</option>@foreach(\App\Enums\PhoneInteractionMatchStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('match_status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Filter</button>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto"><table class="w-full min-w-[1000px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Caller</th><th class="px-5 py-3">Activity</th><th class="px-5 py-3">Summary</th><th class="px-5 py-3">CRM match</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($interactions as $interaction)
                    <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="whitespace-nowrap px-5 py-4"><a href="{{ route('phone-interactions.show', $interaction) }}" class="font-semibold hover:text-amber-600">{{ $interaction->occurred_at->format('M j, Y') }}</a><p class="mt-1 text-xs text-slate-500">{{ $interaction->occurred_at->format('g:i A') }}</p></td>
                        <td class="px-5 py-4"><p class="font-medium">{{ $interaction->display_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $interaction->caller_phone ?: 'No phone supplied' }}</p></td>
                        <td class="px-5 py-4"><p>{{ $interaction->event_type->label() }}</p><p class="mt-1 text-xs text-slate-500">{{ $interaction->direction->label() }}{{ $interaction->duration_seconds !== null ? ' · '.gmdate('i:s', $interaction->duration_seconds) : '' }}</p></td>
                        <td class="max-w-lg px-5 py-4 text-slate-600 dark:text-slate-300">{{ str($interaction->summary ?: 'No summary supplied')->limit(180) }}</td>
                        <td class="px-5 py-4">@if($interaction->contact)<a href="{{ route('contacts.show', $interaction->contact) }}" class="font-semibold text-emerald-700 hover:underline dark:text-emerald-400">{{ $interaction->contact->full_name }}</a>@else<span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' => $interaction->match_status === \App\Enums\PhoneInteractionMatchStatus::Unmatched, 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' => $interaction->match_status === \App\Enums\PhoneInteractionMatchStatus::Conflicting])>{{ $interaction->match_status->label() }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-14 text-center text-slate-500">No Beside activity matches the current filters.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $interactions->links() }}</div>
    </section>
</x-layouts.app>
