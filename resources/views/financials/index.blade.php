<x-layouts.app title="Financials" heading="Financials" subheading="Property economics and payment splits">
    <div class="mx-auto max-w-7xl space-y-6">
        <section class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-6 shadow-sm dark:border-amber-900/70 dark:from-amber-950/30 dark:to-slate-900">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"><div><h2 class="text-lg font-bold">Financial operations workspace</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">Research, Bidding, and Archived properties are excluded from every property summary value. Projected totals use Owned through Under Contract; property actuals also include Sold. Positive property profit is allocated 20% to VVR, 40% to Contact 1, and 40% to Contact 2.@if($canViewSurplusFinancials) Paid Surplus fees flow directly to realized profit and never enter portfolio value, property sales, or property payment splits. Claimant recovery money is tracked separately and never counted as VVR revenue.@endif</p></div><span class="self-start rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Active</span></div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Portfolio Value', $totals->expected_sales_price],
                ['Total all-in', $totals->all_in_amount],
                ['Expected profit', $totals->expected_profit],
                ['Actual sales', $totals->actual_sales_price],
                ...($canViewSurplusFinancials ? [
                    ['Property actual profit', $totals->actual_profit],
                    ['Surplus realized profit', $surplusRealizedProfit],
                    ['Combined actual profit', $combinedActualProfit],
                    ['Claimant money recovered', $surplusRecoveredForClaimants],
                ] : [['Actual profit', $totals->actual_profit]]),
            ] as [$label, $value])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-3 text-xl font-bold">{{ ((float) $value) < 0 ? '−$'.number_format(abs((float) $value), 2) : '$'.number_format((float) $value, 2) }}</p></article>
            @endforeach
        </section>

        @if($canViewSurplusFinancials)<section class="overflow-hidden rounded-2xl border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-start dark:border-slate-800"><div><h2 class="font-semibold">Surplus realized profit</h2><p class="mt-1 text-sm text-slate-500">Only <strong>Actual fee received</strong> on cases with a Paid date counts as VVR profit. Recovered claimant funds are shown for operational tracking only.</p></div>@if($surplusNeedsReconciliation)<span class="self-start rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200">{{ $surplusNeedsReconciliation }} paid {{ str('case')->plural($surplusNeedsReconciliation) }} missing actual fee</span>@else<span class="self-start rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">Reconciled</span>@endif</div>
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60"><tr><th class="px-5 py-3">Case</th><th class="px-5 py-3">Claimant</th><th class="px-5 py-3">Parcel</th><th class="px-5 py-3">Paid</th><th class="px-5 py-3">Recovered for claimant</th><th class="px-5 py-3">VVR actual profit</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($surplusReceipts as $case)<tr><td class="px-5 py-4"><a href="{{ route('surplus.show',$case) }}" class="font-semibold hover:text-amber-600">{{ $case->case_number }}</a></td><td class="px-5 py-4">@if($case->claimantContact)<a href="{{ route('contacts.show',$case->claimantContact) }}" class="hover:text-amber-600">{{ $case->claimantContact->full_name }}</a>@else<span class="text-slate-500">Unlinked</span>@endif</td><td class="px-5 py-4 font-mono text-xs">{{ $case->parcel_id ?: '—' }}</td><td class="px-5 py-4">{{ $case->paid_at?->format('M j, Y') }}</td><td class="px-5 py-4">{{ $case->recovered_amount !== null ? '$'.number_format((float)$case->recovered_amount,2) : '—' }}</td><td class="px-5 py-4 font-semibold {{ $case->actual_fee === null ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-400' }}">{{ $case->actual_fee !== null ? '$'.number_format((float)$case->actual_fee,2) : 'Needs reconciliation' }}</td></tr>@empty<tr><td colspan="6" class="px-6 py-12 text-center text-slate-500">No paid Surplus receipts have been recorded yet.</td></tr>@endforelse
            </tbody></table></div>
        </section>@endif

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800"><h2 class="font-semibold">Property financials</h2><p class="mt-1 text-sm text-slate-500">Configure authoritative amounts and assign the two 40% payment recipients.</p></div>
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60"><tr><th class="px-5 py-3">Property</th><th class="px-5 py-3">All-in / expected sale</th><th class="px-5 py-3">Expected profit</th><th class="px-5 py-3">Payment split</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($properties as $property)
                    @php($calculation = $calculations->get($property->id)['expected'])
                    <tr>
                        <td class="px-5 py-4"><a href="{{ route('properties.show',$property) }}" class="font-semibold hover:text-amber-600">{{ $property->address }}</a><p class="mt-1 text-xs text-slate-500">{{ $property->city }}, {{ $property->state }}</p></td>
                        <td class="px-5 py-4"><p>{{ $property->all_in_amount !== null ? '$'.number_format((float) $property->all_in_amount, 2) : '—' }}</p><p class="mt-1 text-xs text-slate-500">Sale: {{ $property->expected_sales_price !== null ? '$'.number_format((float) $property->expected_sales_price, 2) : '—' }}</p></td>
                        <td class="px-5 py-4"><p @class(['font-semibold', 'text-rose-600 dark:text-rose-400' => $calculation['profit'] !== null && (float) $calculation['profit'] < 0, 'text-emerald-700 dark:text-emerald-400' => $calculation['profit'] !== null && (float) $calculation['profit'] >= 0])>{{ $calculation['profit'] !== null ? (((float) $calculation['profit'] < 0 ? '−$' : '$').number_format(abs((float) $calculation['profit']), 2)) : 'Needs amounts' }}</p></td>
                        <td class="px-5 py-4"><p class="text-xs"><span class="font-semibold">20% VVR:</span> {{ $calculation['vvr_amount'] !== null ? '$'.number_format((float) $calculation['vvr_amount'], 2) : '—' }}</p><p class="mt-1 text-xs"><span class="font-semibold">40% {{ $property->financialSplit?->contactOne?->full_name ?? 'Unassigned' }}:</span> {{ $calculation['contact_one_amount'] !== null ? '$'.number_format((float) $calculation['contact_one_amount'], 2) : '—' }}</p><p class="mt-1 text-xs"><span class="font-semibold">40% {{ $property->financialSplit?->contactTwo?->full_name ?? 'Unassigned' }}:</span> {{ $calculation['contact_two_amount'] !== null ? '$'.number_format((float) $calculation['contact_two_amount'], 2) : '—' }}</p></td>
                        <td class="px-5 py-4 text-right"><a href="{{ route('financials.properties.edit',$property) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:border-amber-400 dark:border-slate-700">Configure</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-14 text-center text-slate-500">No properties are available.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $properties->links() }}</div>
        </section>
    </div>
</x-layouts.app>
