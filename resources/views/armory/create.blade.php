<x-layouts.app title="Add Script" heading="Add to Armory" subheading="Store a governed internal script">
    <form method="POST" action="{{ route('armory.store') }}" enctype="multipart/form-data" class="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('armory._form')
    </form>
</x-layouts.app>
