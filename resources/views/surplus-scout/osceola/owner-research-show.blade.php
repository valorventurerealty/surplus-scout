<x-layouts.app title="Osceola Owner Research" heading="Osceola Owner Research Batch #{{ $batch->id }}" subheading="Exact-parcel research and historical TRIM audit trail">
    <div class="mx-auto max-w-7xl space-y-6" @if(in_array($batch->status, ['queued', 'running'], true)) x-data x-init="setTimeout(() => location.reload(), 15000)" @endif>
        <a href="{{ route('surplus-scout.osceola.index') }}" class="text-sm font-semibold text-slate-600 hover:text-emerald-700">← Osceola Surplus Research</a>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p><h2 class="mt-1 text-2xl font-bold">{{ str($batch->status)->headline() }}</h2><p class="mt-2 text-sm text-slate-500">Triggered by {{ $batch->triggeredBy?->name ?? 'System' }} · {{ str($batch->mode)->headline() }}</p></div><div class="text-right"><p class="text-3xl font-black">{{ $batch->processed_cases }}/{{ $batch->total_cases }}</p><p class="text-xs text-slate-500">cases processed</p></div></div>
            @if(in_array($batch->status, ['queued', 'running'], true))<div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-emerald-600" style="width: {{ $batch->total_cases > 0 ? min(100, ($batch->processed_cases / $batch->total_cases) * 100) : 0 }}%"></div></div>@endif
        </section>
        @php($activeAttempt = $batch->attempts->firstWhere('status', 'running'))
        @if($activeAttempt)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                <p class="text-xs font-semibold uppercase tracking-wide">Researching owner {{ min($batch->processed_cases + 1, $batch->total_cases) }} of {{ $batch->total_cases }}</p>
                <div class="mt-3 grid gap-3 text-sm md:grid-cols-3"><div><span class="block text-xs opacity-70">Parcel</span><strong class="font-mono">{{ $activeAttempt->parcel_searched }}</strong></div><div><span class="block text-xs opacity-70">Step</span><strong>{{ $activeAttempt->current_owner_found ? 'Checking historical TRIM' : 'Verifying Property Appraiser parcel' }}</strong></div><div><span class="block text-xs opacity-70">Current owner</span><strong>{{ $activeAttempt->current_owner_found ?: 'Pending' }}</strong></div></div>
            </section>
        @endif
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([['Owners Verified',$batch->verified_owners],['Ready for Skip Trace',$batch->ready_for_skip_trace],['Business Research',$batch->business_research_needed],['Estate Research',$batch->estate_research_needed],['Trust Research',$batch->trust_research_needed],['Manual Review',$batch->manual_review],['Errors',$batch->errors]] as [$label,$value])<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold">{{ number_format($value) }}</p></div>@endforeach
        </section>
        <section class="space-y-4">
            @forelse($batch->attempts as $attempt)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><a href="{{ route('surplus.show', $attempt->surplusCase) }}" class="font-bold text-emerald-700">{{ $attempt->surplusCase->case_number }}</a><p class="font-mono text-xs text-slate-500">{{ $attempt->parcel_searched }}</p></div><div class="text-right"><p class="font-semibold">{{ App\Enums\SurplusOwnerResearchStatus::tryFrom($attempt->status)?->label() ?? str($attempt->status)->headline() }}</p><p class="text-xs text-slate-500">Attempt {{ $attempt->attempt_number }}</p></div></div>
                    <dl class="mt-4 grid gap-3 text-sm md:grid-cols-3"><div><dt class="text-slate-500">Current owner</dt><dd class="font-medium">{{ $attempt->current_owner_found ?: 'Not found' }}</dd></div><div><dt class="text-slate-500">Historical owner</dt><dd class="font-medium">{{ $attempt->historical_owner_found ?: 'Not verified' }}</dd></div><div><dt class="text-slate-500">TRIM / classification</dt><dd class="font-medium">{{ $attempt->selected_trim_year ?: '—' }} · {{ $attempt->classification ? App\Enums\SurplusOwnerType::tryFrom($attempt->classification)?->label() : '—' }}</dd></div></dl>
                    @if($attempt->research_notes)<div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800">{{ $attempt->research_notes }}</div>@endif
                    @if($attempt->events->isNotEmpty())<ol class="mt-4 grid gap-2 text-xs text-slate-500 md:grid-cols-2">@foreach($attempt->events->sortBy('occurred_at') as $event)<li><span class="font-semibold text-slate-700 dark:text-slate-200">{{ $event->event }}</span> · {{ $event->occurred_at->format('M j, g:i:s A') }}</li>@endforeach</ol>@endif
                </article>
            @empty<div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">The queued jobs have not started yet. The Namecheap cron worker processes them sequentially.</div>@endforelse
        </section>
        <p class="text-sm text-slate-500">These results identify potential claimants or research subjects from public records. They do not establish legal entitlement, heirship, beneficiary status, present residence, or guaranteed payment.</p>
    </div>
</x-layouts.app>
