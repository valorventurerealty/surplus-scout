<x-layouts.app title="Surplus Scout" heading="Surplus Scout" subheading="County research automation with controlled imports into the existing Surplus CRM">
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-amber-50 p-6 shadow-sm dark:border-emerald-900/70 dark:from-emerald-950/30 dark:via-slate-900 dark:to-amber-950/20">
            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">Phase 1</span>
            <h2 class="mt-4 text-2xl font-bold">Osceola Clerk surplus research</h2>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600 dark:text-slate-300">Retrieve the current Clerk report, extract and normalize its records, detect existing cases and amount changes, and preserve a complete run history. This phase does not perform owner research, skip tracing, mailers, or outreach.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('surplus-scout.osceola.index') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Open Osceola Research</a>
                <a href="{{ route('surplus.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900">Open Surplus cases</a>
            </div>
        </section>
        <section class="grid gap-4 md:grid-cols-3">
            @foreach([
                ['Osceola', 'Available now', 'Clerk PDF ingestion and case synchronization.', true],
                ['Orange', 'Future phase', 'County-specific adapter not configured.', false],
                ['Additional county', 'Future phase', 'Reserved for the third county adapter.', false],
            ] as [$county, $status, $description, $active])
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between"><h3 class="font-semibold">{{ $county }}</h3><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $status }}</span></div>
                    <p class="mt-3 text-sm leading-6 text-slate-500">{{ $description }}</p>
                </article>
            @endforeach
        </section>
    </div>
</x-layouts.app>
