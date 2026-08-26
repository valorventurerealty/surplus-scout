<x-layouts.app title="Tasks" heading="Tasks" subheading="Assignments, deadlines, recurring work, and reminders">
    @php
        $canBulkUpdate = auth()->user()->canManageTasks();
        $pageTaskIds = $tasks->pluck('id')->values();
    @endphp

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([['Open tasks', $metrics['open']], ['Overdue', $metrics['overdue']], ['Due today', $metrics['due_today']], ['Assigned to me', $metrics['mine']]] as [$label, $value])
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-bold">{{ $value }}</p>
            </section>
        @endforeach
    </div>

    <div class="mb-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-start">
        <form method="GET" class="grid flex-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(180px,1fr)_135px_120px_160px_135px_130px_auto]">
            @if(request('sort'))
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">
            @endif
            <input name="search" value="{{ request('search') }}" placeholder="Search tasks" class="form-input mt-0">
            <select name="status" class="form-input mt-0">
                <option value="">All statuses</option>
                @foreach(\App\Enums\TaskStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
            <select name="priority" class="form-input mt-0">
                <option value="">All priorities</option>
                @foreach(\App\Enums\TaskPriority::cases() as $priority)<option value="{{ $priority->value }}" @selected(request('priority') === $priority->value)>{{ $priority->label() }}</option>@endforeach
            </select>
            <select name="assigned_user_id" class="form-input mt-0">
                <option value="">All assignees</option>
                @foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected((string) request('assigned_user_id') === (string) $assignee->id)>{{ $assignee->name }}</option>@endforeach
            </select>
            <select name="due" class="form-input mt-0">
                <option value="">Any due date</option>
                <option value="overdue" @selected(request('due') === 'overdue')>Overdue</option>
                <option value="today" @selected(request('due') === 'today')>Due today</option>
                <option value="next_7_days" @selected(request('due') === 'next_7_days')>Next 7 days</option>
                <option value="no_due" @selected(request('due') === 'no_due')>No due date</option>
            </select>
            <select name="record_type" class="form-input mt-0">
                <option value="">All records</option>
                <option value="contact" @selected(request('record_type') === 'contact')>Contacts</option>
                <option value="property" @selected(request('record_type') === 'property')>Properties</option>
                <option value="deal" @selected(request('record_type') === 'deal')>Deals</option>
                @if(auth()->user()->canViewSurplusCases())<option value="surplus" @selected(request('record_type') === 'surplus')>Surplus cases</option>@endif
                @if(auth()->user()->canViewPreAuctionAcquisitions())<option value="pre_auction" @selected(request('record_type') === 'pre_auction')>PreTax Auction acquisitions</option>@endif
                <option value="standalone" @selected(request('record_type') === 'standalone')>Standalone</option>
            </select>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Filter</button>
        </form>
        <div class="flex gap-2">
            @can('viewAny', \App\Models\TaskTemplate::class)<a href="{{ route('task-templates.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium dark:border-slate-700">Templates</a>@endcan
            @can('create', \App\Models\Task::class)<a href="{{ route('tasks.create') }}" class="rounded-lg bg-amber-400 px-4 py-2 text-center text-sm font-semibold text-slate-950 hover:bg-amber-300">+ Add task</a>@endcan
        </div>
    </div>

    <section
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        @if($canBulkUpdate) x-data="{ selected: [], pageIds: @js($pageTaskIds), togglePage() { this.selected = this.allPageSelected() ? [] : [...this.pageIds] }, allPageSelected() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selected.includes(id)) } }" @endif
    >
        @if($canBulkUpdate)
            <form id="task-bulk-status-form" method="POST" action="{{ route('tasks.bulk-status') }}" @submit="if (selected.length === 0 || !confirm(`Change ${selected.length} selected task${selected.length === 1 ? '' : 's'} to the chosen status?`)) $event.preventDefault()">
                @csrf
                @method('PATCH')
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-800 dark:bg-slate-800/40 sm:flex-row sm:items-center">
                    <p class="min-w-32 text-sm font-semibold"><span x-text="selected.length">0</span> selected</p>
                    <select name="status" required class="form-input mt-0 sm:max-w-xs">
                        <option value="">Choose new status</option>
                        @foreach(\App\Enums\TaskStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" :disabled="selected.length === 0" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:opacity-50">Change selected statuses</button>
                    <button type="button" x-show="selected.length > 0" @click="selected = []" class="text-sm font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Clear selection</button>
                </div>
            </form>
            @error('task_ids')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
            @error('task_ids.*')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
            @error('status')<p class="px-5 pt-4 text-sm font-medium text-rose-600">{{ $message }}</p>@enderror
        @endif

        @if(!request('status'))
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/70 px-5 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800/20 dark:text-slate-300">
                <span>Completed tasks are hidden from the default view.</span>
                <a href="{{ route('tasks.index', [...request()->except('page'), 'status' => \App\Enums\TaskStatus::Completed->value]) }}" class="font-semibold text-amber-700 hover:underline dark:text-amber-400">View completed tasks</a>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/60">
                    <tr>
                        @if($canBulkUpdate)
                            <th class="w-12 px-5 py-3">
                                <input type="checkbox" :checked="allPageSelected()" @change="togglePage()" aria-label="Select all tasks on this page" class="rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                            </th>
                        @endif
                        <x-sortable-header route="tasks.index" column="task" label="Task" />
                        <x-sortable-header route="tasks.index" column="associated_record" label="Associated record" />
                        <x-sortable-header route="tasks.index" column="assigned_to" label="Assigned to" />
                        <x-sortable-header route="tasks.index" column="due" label="Due" />
                        <x-sortable-header route="tasks.index" column="priority" label="Priority" />
                        <x-sortable-header route="tasks.index" column="status" label="Status" />
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($tasks as $task)
                        <tr class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/40" @if($canBulkUpdate) :class="selected.includes({{ $task->id }}) ? 'bg-amber-50/60 dark:bg-amber-950/20' : ''" @endif>
                            @if($canBulkUpdate)
                                <td class="px-5 py-4">
                                    <input type="checkbox" name="task_ids[]" value="{{ $task->id }}" form="task-bulk-status-form" x-model.number="selected" aria-label="Select {{ $task->title }}" class="rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                                </td>
                            @endif
                            <td class="px-5 py-4"><a href="{{ route('tasks.show', $task) }}" class="font-semibold hover:text-amber-600">{{ $task->title }}</a>@if($task->recurrence_frequency)<p class="mt-1 text-xs text-slate-500">Repeats every {{ $task->recurrence_interval > 1 ? $task->recurrence_interval.' ' : '' }}{{ str($task->recurrence_frequency->value)->plural($task->recurrence_interval)->lower() }}</p>@endif</td>
                            <td class="px-5 py-4">@if($task->taskable instanceof \App\Models\Contact)<a href="{{ route('contacts.show', $task->taskable) }}" class="hover:text-amber-600">{{ $task->taskable->full_name }}</a><p class="mt-1 text-xs text-slate-500">Contact</p>@elseif($task->taskable instanceof \App\Models\Property)<a href="{{ route('properties.show', $task->taskable) }}" class="hover:text-amber-600">{{ $task->taskable->address }}</a><p class="mt-1 text-xs text-slate-500">Property</p>@elseif($task->taskable instanceof \App\Models\Deal)<a href="{{ route('deals.show', $task->taskable) }}" class="hover:text-amber-600">{{ $task->taskable->title }}</a><p class="mt-1 text-xs text-slate-500">Deal</p>@elseif($task->taskable instanceof \App\Models\SurplusCase)<a href="{{ route('surplus.show', $task->taskable) }}" class="hover:text-amber-600">{{ $task->taskable->case_number }}</a><p class="mt-1 text-xs text-slate-500">Surplus case</p>@elseif($task->taskable instanceof \App\Models\PreAuctionAcquisition)<a href="{{ route('pre-auction.show', $task->taskable) }}" class="hover:text-amber-600">{{ $task->taskable->case_number }}</a><p class="mt-1 text-xs text-slate-500">PreTax Auction acquisition</p>@else<span class="text-slate-400">Standalone</span>@endif</td>
                            <td class="px-5 py-4">{{ $task->assignedUser?->name ?? 'Unassigned' }}</td>
                            <td class="px-5 py-4"><span @class(['font-semibold text-rose-600 dark:text-rose-400' => $task->due_at?->isPast() && $task->status !== \App\Enums\TaskStatus::Completed])>{{ $task->due_at?->format('M j, Y') ?? '—' }}</span>@if($task->due_at)<p class="mt-1 text-xs text-slate-500">{{ $task->due_at->format('g:i A') }}</p>@endif</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-800">{{ $task->priority->label() }}</span></td>
                            <td class="px-5 py-4">{{ $task->status->label() }}</td>
                            <td class="px-5 py-4">@if($task->status !== \App\Enums\TaskStatus::Completed) @can('update', $task)<form method="POST" action="{{ route('tasks.complete', $task) }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-400">Complete</button></form>@endcan @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 7 + ($canBulkUpdate ? 1 : 0) }}" class="px-5 py-14 text-center text-slate-500">No tasks match the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $tasks->links() }}</div>
    </section>
</x-layouts.app>
