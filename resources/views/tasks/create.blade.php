<x-layouts.app title="Add Task" heading="Add task" subheading="Assign and schedule accountable work">
    <form method="POST" action="{{ route('tasks.store') }}" class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">@include('tasks._form')</form>
</x-layouts.app>
