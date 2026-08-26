<x-layouts.app title="Edit property" heading="Edit property" subheading="{{ $property->full_address }}">
    <form method="POST" action="{{ route('properties.update', $property) }}" class="mx-auto max-w-6xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('properties._form')
    </form>
</x-layouts.app>
