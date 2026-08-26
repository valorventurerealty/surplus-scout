<x-layouts.app title="Email" heading="Email" subheading="Controlled outbound communication from info@valorventure.us">
    @include('email._navigation', ['active' => 'messages'])
    @include('email._validation-summary')
    <form method="GET" class="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-[1fr_220px_auto]">
        <input name="search" value="{{ request('search') }}" class="form-input" placeholder="Search subject">
        <select name="status" class="form-input"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
        <button class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white dark:bg-amber-400 dark:text-slate-950">Filter</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/60"><tr><th class="px-5 py-3">Subject</th><th class="px-5 py-3">Recipient</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Sender</th><th class="px-5 py-3">Created / sent</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse($emails as $email)<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40"><td class="px-5 py-4"><a href="{{ route('email.show', $email) }}" class="font-semibold hover:text-amber-600">{{ $email->subject }}</a>@if($email->primaryContact)<p class="mt-1 text-xs text-slate-500">{{ trim($email->primaryContact->first_name.' '.$email->primaryContact->last_name) }}</p>@endif</td><td class="px-5 py-4">{{ implode(', ', $email->to_json) }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold dark:bg-slate-800">{{ $email->status->label() }}</span></td><td class="px-5 py-4">{{ $email->user->name }}</td><td class="px-5 py-4 text-xs text-slate-500">{{ $email->created_at->format('M j, Y g:i A') }}@if($email->sent_at)<span class="block text-emerald-600">Sent {{ $email->sent_at->format('M j, g:i A') }}</span>@endif</td></tr>
        @empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No messages match these filters.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="mt-5">{{ $emails->links() }}</div>
</x-layouts.app>
