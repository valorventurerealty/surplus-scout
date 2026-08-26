<x-layouts.app title="Add property" heading="Add property manually" subheading="Enter a verified property record without AI">
    @if(auth()->user()->canUseVvrAi())
        <div class="mx-auto mb-6 flex max-w-6xl flex-col justify-between gap-3 rounded-xl border border-violet-200 bg-violet-50 p-4 sm:flex-row sm:items-center dark:border-violet-900 dark:bg-violet-950/30">
            <div><p class="text-sm font-semibold">Want VVR AI to prepare this record?</p><p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Describe the property in a prompt and optionally attach a source document.</p></div>
            <a href="{{ route('vvr-ai.index') }}" class="self-start rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Open VVR AI</a>
        </div>
    @endif

    <form method="POST" action="{{ route('properties.store') }}" class="mx-auto max-w-6xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('properties._form')
    </form>
</x-layouts.app>
