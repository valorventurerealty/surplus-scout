<x-layouts.app title="Edit Email Draft" heading="Edit Email Draft" subheading="Changes remain private until you explicitly approve delivery">
    @include('email._navigation', ['active' => 'compose'])
    <form id="email-compose-form" method="POST" action="{{ route('email.update', $email) }}" enctype="multipart/form-data" class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @include('email._validation-summary')
        @include('email._form')
    </form>
</x-layouts.app>
