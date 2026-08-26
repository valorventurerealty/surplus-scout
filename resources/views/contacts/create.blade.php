<x-layouts.app title="Add Contact" heading="Add contact" subheading="Create a new relationship record">
    <form method="POST" action="{{ route('contacts.store') }}" class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('contacts._form')
    </form>
</x-layouts.app>
