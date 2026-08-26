<x-layouts.app title="Add Email Template" heading="Add Email Template" subheading="Create reusable email copy inside Armory">
    @include('armory._navigation', ['active' => 'email-templates'])
    <form id="email-template-form" method="POST" action="/armory/email-templates/save" enctype="multipart/form-data" novalidate class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('armory.email-templates._form')
    </form>
</x-layouts.app>
