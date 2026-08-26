<x-layouts.app title="Edit {{ $template->name }}" heading="Edit Email Template" subheading="Update governed email copy">
    @include('armory._navigation', ['active' => 'email-templates'])
    <form id="email-template-form" method="POST" action="{{ route('armory.email-templates.update', $template) }}" enctype="multipart/form-data" novalidate class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('armory.email-templates._form')
    </form>
</x-layouts.app>
