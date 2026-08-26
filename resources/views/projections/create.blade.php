<x-layouts.app title="New projection scenario" heading="New projection scenario" subheading="Create a governed planning horizon">
    <form method="POST" action="{{ route('projections.store') }}" class="mx-auto max-w-5xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @include('projections._metadata', ['scenario' => null])
        <div class="flex justify-end gap-3 border-t border-slate-200 pt-5 dark:border-slate-800"><a href="{{ route('projections.index') }}" class="rounded-lg border px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-lg bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950">Create scenario</button></div>
    </form>
</x-layouts.app>
