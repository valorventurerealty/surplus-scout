<x-layouts.app title="Edit Negotiation" heading="Edit negotiation plan" subheading="Update {{ $negotiation->name }}">
    <form method="POST" action="{{ route('armory.negotiations.update',$negotiation) }}" class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('armory.negotiations._form')
    </form>
</x-layouts.app>
