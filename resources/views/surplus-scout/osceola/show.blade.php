<x-layouts.app title="Osceola Research Run" heading="Osceola Research Run #{{ $run->id }}" subheading="Traceable Clerk import result">
    <div class="mx-auto max-w-6xl space-y-6" @if($run->status->active()) x-data x-init="setTimeout(() => location.reload(), 15000)" @endif>
        <a href="{{ route('surplus-scout.osceola.index') }}" class="text-sm font-semibold text-slate-600 hover:text-emerald-700">← Osceola Research</a>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</p><h2 class="mt-1 text-2xl font-bold">{{ $run->status->label() }}</h2></div><div class="text-right text-sm text-slate-500"><p>Triggered by {{ $run->triggeredBy?->name ?? 'System' }}</p><p>{{ $run->started_at?->format('M j, Y g:i A') ?? $run->created_at->format('M j, Y g:i A') }}</p></div></div>
            @if($run->error_message)<div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><strong>Run failed:</strong> {{ $run->error_message }}<p class="mt-2">Existing Surplus data was preserved; removal detection was not applied.</p></div>@endif
            <dl class="mt-6 grid gap-4 text-sm md:grid-cols-2"><div><dt class="text-slate-500">Source</dt><dd class="mt-1 font-medium"><a class="text-emerald-700 underline" href="{{ $run->source_url }}" target="_blank" rel="noopener noreferrer">Osceola County Clerk report ↗</a></dd></div><div><dt class="text-slate-500">Clerk report date</dt><dd class="mt-1 font-medium">{{ $run->source_report_date?->format('M j, Y') ?? 'Not validated' }}</dd></div><div><dt class="text-slate-500">File SHA-256</dt><dd class="mt-1 break-all font-mono text-xs">{{ $run->source_file_hash ?? 'Not downloaded' }}</dd></div><div><dt class="text-slate-500">Completed</dt><dd class="mt-1 font-medium">{{ $run->completed_at?->format('M j, Y g:i A') ?? 'Not completed' }}</dd></div></dl>
        </section>
        <section class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach([['Records Reviewed',$run->records_found],['Existing Records',$run->existing_records],['New Leads',$run->new_records],['Amount Changes',$run->amount_changes],['No Longer Listed',$run->removed_records],['Warnings',$run->warning_count]] as [$label,$value])
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-bold">{{ number_format($value) }}</p></div>
            @endforeach
        </section>
        @if($run->warnings)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950/30"><h2 class="font-semibold text-amber-900 dark:text-amber-200">Extraction warnings</h2><ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-amber-900/90 dark:text-amber-200/90">@foreach($run->warnings as $warning)<li>{{ $warning }}</li>@endforeach</ul></section>
        @endif
        <p class="text-sm text-slate-500">A missing Clerk row is labeled “No Longer Listed” only after a complete download, parse, structural validation, and transactional import. VVR does not infer that the funds were claimed.</p>
    </div>
</x-layouts.app>
