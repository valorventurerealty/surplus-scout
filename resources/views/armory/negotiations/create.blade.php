<x-layouts.app title="New Negotiation" heading="New negotiation plan" subheading="Build a deterministic buyer price ladder">
    <form method="POST" action="{{ route('armory.negotiations.store') }}" class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('armory.negotiations._form')
    </form>
</x-layouts.app>
