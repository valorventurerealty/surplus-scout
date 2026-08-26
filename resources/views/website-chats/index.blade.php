<x-layouts.app title="Website Chats" heading="Website Chats" subheading="Completed Valorie conversations and follow-up activity">
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach([['Open', $metrics['open']], ['Today', $metrics['today']], ['All chats', $metrics['total']]] as [$label, $value])
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold">{{ number_format($value) }}</p></section>
        @endforeach
    </div>
    <form method="GET" class="mb-6 grid gap-3 sm:grid-cols-[minmax(220px,1fr)_200px_150px_auto]">
        <input name="search" value="{{ request('search') }}" placeholder="Search visitor, property, or parcel" class="form-input mt-0">
        <select name="topic" class="form-input mt-0"><option value="">All topics</option>@foreach(['seller'=>'Sell a property','tax_auction'=>'Property facing tax auction','surplus'=>'Surplus funds','other'=>'Something else'] as $value=>$label)<option value="{{ $value }}" @selected(request('topic') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="status" class="form-input mt-0"><option value="">All statuses</option><option value="open" @selected(request('status') === 'open')>Open</option><option value="resolved" @selected(request('status') === 'resolved')>Resolved</option></select>
        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Filter</button>
    </form>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60"><tr><th class="px-5 py-3">Received</th><th class="px-5 py-3">Visitor</th><th class="px-5 py-3">Topic</th><th class="px-5 py-3">Property</th><th class="px-5 py-3">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($conversations as $conversation)<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40"><td class="whitespace-nowrap px-5 py-4"><a href="{{ route('website-chats.show',$conversation) }}" class="font-semibold hover:text-amber-600">{{ $conversation->submitted_at->format('M j, Y') }}</a><p class="mt-1 text-xs text-slate-500">{{ $conversation->submitted_at->format('g:i A') }}</p></td><td class="px-5 py-4"><p class="font-medium">{{ $conversation->visitor_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $conversation->visitor_email }}</p></td><td class="px-5 py-4">{{ $conversation->topic_label }}</td><td class="px-5 py-4">{{ $conversation->property_address ?: ($conversation->parcel_id ? 'Parcel '.$conversation->parcel_id : 'Not supplied') }}</td><td class="px-5 py-4"><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold','bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'=>$conversation->status==='open','bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'=>$conversation->status==='resolved'])>{{ str($conversation->status)->headline() }}</span></td></tr>@empty<tr><td colspan="5" class="px-5 py-14 text-center text-slate-500">No website chats match the current filters.</td></tr>@endforelse</tbody></table></div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $conversations->links() }}</div>
    </section>
</x-layouts.app>
