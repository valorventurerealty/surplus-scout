<x-layouts.app title="Edit Task" heading="Edit task" subheading="Update assignment, schedule, or recurrence">
    <form method="POST" action="{{ route('tasks.update', $task) }}" class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">@include('tasks._form')</form>
</x-layouts.app>
