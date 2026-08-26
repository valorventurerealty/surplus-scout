<x-layouts.app title="Edit Script" heading="Edit Armory script" subheading="Update {{ $script->title }}">
    <form method="POST" action="{{ route('armory.update', $script) }}" class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('armory._form')
    </form>
</x-layouts.app>
