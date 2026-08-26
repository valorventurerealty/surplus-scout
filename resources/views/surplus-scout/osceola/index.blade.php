<x-layouts.app title="Osceola Surplus Research" heading="Osceola County Surplus Research" subheading="Current Clerk report ingestion, duplicate detection, and run history">
    <div class="mx-auto max-w-7xl space-y-6" @if($activeRun || $activeOwnerBatch) x-data x-init="setTimeout(() => location.reload(), 15000)" @endif>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('surplus-scout.index') }}" class="text-sm font-semibold text-slate-600 hover:text-emerald-700">← Surplus Scout</a>
            @can('create', App\Models\SurplusCase::class)
                <form method="POST" action="{{ route('surplus-scout.osceola.runs.store') }}">@csrf
                    <button type="submit" @disabled($activeRun) class="rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">{{ $activeRun ? 'RESEARCH RUN ACTIVE' : 'RUN OSCEOLA RESEARCH' }}</button>
                </form>
            @endcan
        </div>

        @error('research')<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $message }}</div>@enderror
        @if($activeRun)<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Run {{ $activeRun->token }} is {{ strtolower($activeRun->status->label()) }}. This page refreshes while it is active.</div>@endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Last Successful Run', $lastSuccessful?->completed_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? 'Never'],
                ['Last Clerk Report Date', $lastSuccessful?->source_report_date?->format('M j, Y') ?? 'Not available'],
                ['Available Surplus Records', number_format($availableCount)],
                ['Requiring Owner Research', number_format($pendingCount)],
            ] as [$label, $value])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-xl font-bold">{{ $value }}</p></article>
            @endforeach
        </section>

        @if($lastSuccessful)
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Latest results</h2><a href="{{ route('surplus-scout.osceola.runs.show', $lastSuccessful) }}" class="text-sm font-semibold text-emerald-700">View run</a></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
                    @foreach([['Records Reviewed',$lastSuccessful->records_found],['Existing Records',$lastSuccessful->existing_records],['New Leads',$lastSuccessful->new_records],['Amount Changes',$lastSuccessful->amount_changes],['No Longer Listed',$lastSuccessful->removed_records],['Warnings',$lastSuccessful->warning_count]] as [$label,$value])
                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold">{{ number_format($value) }}</p></div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-4 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-6 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/20">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Phase 2</p><h2 class="mt-1 text-xl font-bold">Osceola Owner Research</h2><p class="mt-1 max-w-3xl text-sm text-slate-600 dark:text-slate-300">Exact parcel verification, current owner capture, 2025 TRIM with 2024 fallback, historical-owner classification, and same-case updates. Results identify research subjects only; they do not determine legal entitlement.</p></div>
                @if($activeOwnerBatch)<a href="{{ route('surplus-scout.osceola.owner-research.show', $activeOwnerBatch) }}" class="rounded-lg bg-amber-100 px-4 py-2 text-sm font-semibold text-amber-900">View active batch: {{ $activeOwnerBatch->processed_cases }}/{{ $activeOwnerBatch->total_cases }}</a>@endif
            </div>
            @error('owner_research')<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $message }}</div>@enderror
            @error('case_ids')<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $message }}</div>@enderror
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['Pending Owner Research', $ownerCounts['pending_owner_research'] ?? 0],
                    ['Researching', ($ownerCounts['researching_property'] ?? 0) + ($ownerCounts['researching_historical_owner'] ?? 0)],
                    ['Ready for Skip Trace', $ownerCounts['ready_for_skip_trace'] ?? 0],
                    ['Business Research Needed', $ownerCounts['business_research_needed'] ?? 0],
                    ['Estate / Heir Research Needed', $ownerCounts['estate_heir_research_needed'] ?? 0],
                    ['Trust Research Needed', $ownerCounts['trust_research_needed'] ?? 0],
                    ['Manual Review', ($ownerCounts['manual_review'] ?? 0) + ($ownerCounts['owner_match_unresolved'] ?? 0)],
                    ['Errors', ($ownerCounts['property_appraiser_error'] ?? 0) + ($ownerCounts['parcel_not_found'] ?? 0) + ($ownerCounts['trim_notice_not_found'] ?? 0)],
                ] as [$label, $value])
                    <div class="rounded-xl border border-emerald-100 bg-white p-4 dark:border-emerald-900 dark:bg-slate-900"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold">{{ number_format($value) }}</p></div>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="POST" action="{{ route('surplus-scout.osceola.owner-research.store') }}">@csrf
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div><h2 class="font-semibold">Owner-research cases</h2><p class="text-xs text-slate-500">Pending and retry cases appear first. Selecting a completed case explicitly requests a rerun.</p></div>
                    @can('create', App\Models\SurplusCase::class)<div class="flex flex-wrap gap-2">
                        <button name="mode" value="next_10" @disabled($activeOwnerBatch) class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">Research Next 10</button>
                        <button name="mode" value="all" @disabled($activeOwnerBatch) class="rounded-lg border border-emerald-700 px-3 py-2 text-sm font-semibold text-emerald-800 disabled:opacity-50">Research All Pending</button>
                        <button name="mode" value="selected" @disabled($activeOwnerBatch) class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold disabled:opacity-50">Research Selected</button>
                    </div>@endcan
                </div>
                <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="px-5 py-3"><span class="sr-only">Select</span></th><th class="px-5 py-3">Case / parcel</th><th class="px-5 py-3">Surplus</th><th class="px-5 py-3">Research status</th><th class="px-5 py-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($ownerCases as $case)<tr><td class="px-5 py-4"><input type="checkbox" name="case_ids[]" value="{{ $case->id }}" class="rounded border-slate-300"></td><td class="px-5 py-4"><a href="{{ route('surplus.show', $case) }}" class="font-semibold text-emerald-700">{{ $case->case_number }}</a><p class="font-mono text-xs text-slate-500">{{ $case->parcel_id }}</p></td><td class="px-5 py-4">{{ $case->surplus_amount !== null ? '$'.number_format((float) $case->surplus_amount, 2) : '—' }}</td><td class="px-5 py-4">{{ App\Enums\SurplusOwnerResearchStatus::tryFrom((string) $case->research_status)?->label() ?? str($case->research_status)->headline() }}</td><td class="px-5 py-4">@can('update', $case)<button formaction="{{ route('surplus-scout.osceola.owner-research.case', $case) }}" @disabled($activeOwnerBatch) class="text-sm font-semibold text-emerald-700 disabled:opacity-50">{{ $case->research_status === 'pending_owner_research' ? 'Research Owner' : 'Retry Research' }}</button>@endcan</td></tr>
                    @empty<tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No Osceola Clerk cases are available for owner research.</td></tr>@endforelse
                </tbody></table></div>
            </form>
            <div class="p-4">{{ $ownerCases->links() }}</div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="font-semibold">Owner-research batch history</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="px-5 py-3">Batch</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Progress</th><th class="px-5 py-3">Verified</th><th class="px-5 py-3">Manual</th><th class="px-5 py-3">Errors</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($ownerBatches as $batch)<tr><td class="px-5 py-4"><a href="{{ route('surplus-scout.osceola.owner-research.show', $batch) }}" class="font-semibold text-emerald-700">#{{ $batch->id }}</a><p class="text-xs text-slate-500">{{ $batch->triggeredBy?->name ?? 'System' }}</p></td><td class="px-5 py-4">{{ str($batch->status)->headline() }}</td><td class="px-5 py-4">{{ $batch->processed_cases }}/{{ $batch->total_cases }}</td><td class="px-5 py-4">{{ $batch->verified_owners }}</td><td class="px-5 py-4">{{ $batch->manual_review }}</td><td class="px-5 py-4">{{ $batch->errors }}</td></tr>
                @empty<tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No owner-research batches yet.</td></tr>@endforelse
            </tbody></table></div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="font-semibold">Run history</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800"><tr><th class="px-5 py-3">Run</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Started</th><th class="px-5 py-3">Reviewed</th><th class="px-5 py-3">New</th><th class="px-5 py-3">Changes</th><th class="px-5 py-3">Removed</th><th class="px-5 py-3">Warnings</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($runs as $run)<tr><td class="px-5 py-4"><a class="font-semibold text-emerald-700" href="{{ route('surplus-scout.osceola.runs.show', $run) }}">#{{ $run->id }}</a><p class="text-xs text-slate-500">{{ $run->triggeredBy?->name ?? 'System' }}</p></td><td class="px-5 py-4">{{ $run->status->label() }}</td><td class="px-5 py-4">{{ $run->started_at?->format('M j, g:i A') ?? 'Queued' }}</td><td class="px-5 py-4">{{ $run->records_found }}</td><td class="px-5 py-4">{{ $run->new_records }}</td><td class="px-5 py-4">{{ $run->amount_changes }}</td><td class="px-5 py-4">{{ $run->removed_records }}</td><td class="px-5 py-4">{{ $run->warning_count }}</td></tr>
                @empty<tr><td colspan="8" class="px-5 py-10 text-center text-slate-500">No Osceola research runs yet.</td></tr>@endforelse
            </tbody></table></div><div class="p-4">{{ $runs->links() }}</div>
        </section>
    </div>
</x-layouts.app>
