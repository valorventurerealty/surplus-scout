<div wire:poll.60s>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([['Contacts', $contactCount, 'All active records'], ['Properties', $propertyCount, 'All active records'], ['New contacts', $newContactCount, 'Awaiting triage'], ['My tasks due', $myTasksDue, 'Through end of today'], ['Upcoming auctions', $upcomingAuctionCount, 'Next 30 days']] as [$label, $value, $caption])
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p><p class="mt-2 text-3xl font-bold tracking-tight">{{ $value }}</p><p class="mt-2 text-xs text-slate-400">{{ $caption }}</p></section>
        @endforeach
    </div>
    @if($canViewFinancials)
        <section class="mt-6 grid gap-4 sm:grid-cols-3">
            @foreach([['Portfolio value',$portfolioTotals->value],['Portfolio all-in',$portfolioTotals->all_in],['Expected portfolio profit',$portfolioTotals->profit]] as [$label,$amount])
                <a href="{{ route('financials.index') }}" class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm hover:border-amber-400 dark:border-amber-900 dark:bg-amber-950/20"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold">{{ ((float)$amount < 0 ? '−$' : '$').number_format(abs((float)$amount),2) }}</p><p class="mt-2 text-xs text-slate-400">Live from Property Financials</p></a>
            @endforeach
        </section>
    @endif
    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800"><div><h2 class="font-semibold">Recent contacts</h2><p class="text-sm text-slate-500">Latest additions to the CRM</p></div><a href="{{ route('contacts.index') }}" class="text-sm font-semibold text-amber-600 dark:text-amber-400">View all →</a></div>
        <div class="divide-y divide-slate-100 dark:divide-slate-800">
            @forelse ($recentContacts as $contact)
                <a href="{{ route('contacts.show', $contact) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50"><div><p class="font-medium">{{ $contact->full_name }}</p><p class="text-sm text-slate-500">{{ $contact->company ?: $contact->email ?: 'No company or email' }}</p></div><div class="text-right"><p class="text-sm">{{ $contact->type->label() }}</p><p class="text-xs text-slate-400">{{ $contact->created_at->diffForHumans() }}</p></div></a>
            @empty <p class="px-5 py-12 text-center text-sm text-slate-500">No contacts yet. Add the first one to begin.</p> @endforelse
        </div>
    </section>
</div>
