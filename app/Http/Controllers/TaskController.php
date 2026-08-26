<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskRecurrence;
use App\Enums\TaskStatus;
use App\Enums\ContactType;
use App\Enums\DealType;
use App\Http\Requests\BulkUpdateTaskStatusRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\PreAuctionAcquisition;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Task::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due' => ['nullable', Rule::in(['overdue', 'today', 'next_7_days', 'no_due'])],
            'record_type' => ['nullable', Rule::in(array_values(array_filter(['contact', 'property', 'deal', $request->user()->canViewSurplusCases() ? 'surplus' : null, $request->user()->canViewPreAuctionAcquisitions() ? 'pre_auction' : null, 'standalone'])))],
            'sort' => ['nullable', Rule::in(['task', 'associated_record', 'assigned_to', 'due', 'priority', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $validated['sort'] ?? null;
        $direction = $validated['direction'] ?? 'asc';

        $base = Task::query()
            ->when(! $request->user()->canViewSurplusCases(), fn ($query) => $query->where(fn ($query) => $query->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new SurplusCase)->getMorphClass())))
            ->when(! $request->user()->canViewSurplusCases(), fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new Contact)->getMorphClass())
                ->orWhereNotIn('taskable_id', Contact::query()->select('id')->where('type', ContactType::Surplus->value))))
            ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where(fn ($query) => $query->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new PreAuctionAcquisition)->getMorphClass())))
            ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new Contact)->getMorphClass())
                ->orWhereNotIn('taskable_id', Contact::query()->select('id')->where('type', ContactType::PreTaxAuctions->value))))
            ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new Deal)->getMorphClass())
                ->orWhereNotIn('taskable_id', Deal::query()->select('id')->where('type', DealType::PreTaxAuctionAcquisition->value))));
        $open = (clone $base)->open();
        $metrics = [
            'open' => (clone $open)->count(),
            'overdue' => (clone $open)->where('due_at', '<', now())->count(),
            'due_today' => (clone $open)->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'mine' => (clone $open)->where('assigned_user_id', $request->user()->id)->count(),
        ];

        $tasks = Task::query()
            ->when(! $request->user()->canViewSurplusCases(), fn ($query) => $query->where(fn ($query) => $query->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new SurplusCase)->getMorphClass())))
            ->when(! $request->user()->canViewSurplusCases(), fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new Contact)->getMorphClass())
                ->orWhereNotIn('taskable_id', Contact::query()->select('id')->where('type', ContactType::Surplus->value))))
            ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where(fn ($query) => $query->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new PreAuctionAcquisition)->getMorphClass())))
            ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new Contact)->getMorphClass())
                ->orWhereNotIn('taskable_id', Contact::query()->select('id')->where('type', ContactType::PreTaxAuctions->value))))
            ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('taskable_type')->orWhere('taskable_type', '!=', (new Deal)->getMorphClass())
                ->orWhereNotIn('taskable_id', Deal::query()->select('id')->where('type', DealType::PreTaxAuctionAcquisition->value))))
            ->with(['assignedUser:id,name', 'taskable'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
                fn ($query) => $query->where('status', '!=', TaskStatus::Completed),
            )
            ->when($validated['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($validated['assigned_user_id'] ?? null, fn ($query, $userId) => $query->where('assigned_user_id', $userId))
            ->when($validated['due'] ?? null, function ($query, $due) {
                match ($due) {
                    'overdue' => $query->open()->where('due_at', '<', now()),
                    'today' => $query->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]),
                    'next_7_days' => $query->whereBetween('due_at', [now(), now()->addDays(7)]),
                    'no_due' => $query->whereNull('due_at'),
                };
            })
            ->when($validated['record_type'] ?? null, function ($query, $type) {
                match ($type) {
                    'contact' => $query->where('taskable_type', (new Contact)->getMorphClass()),
                    'property' => $query->where('taskable_type', (new Property)->getMorphClass()),
                    'deal' => $query->where('taskable_type', (new Deal)->getMorphClass()),
                    'surplus' => $query->where('taskable_type', (new SurplusCase)->getMorphClass()),
                    'pre_auction' => $query->where('taskable_type', (new PreAuctionAcquisition)->getMorphClass()),
                    'standalone' => $query->whereNull('taskable_type'),
                };
            });

        if ($sort) {
            match ($sort) {
                'task' => $tasks->orderBy('title', $direction),
                'associated_record' => $tasks
                    ->orderByRaw('taskable_type is null')
                    ->orderBy('taskable_type', $direction)
                    ->orderBy(Contact::query()->select('last_name')->whereColumn('contacts.id', 'tasks.taskable_id')->whereRaw('tasks.taskable_type = ?', [(new Contact)->getMorphClass()])->limit(1), $direction)
                    ->orderBy(Property::query()->select('address')->whereColumn('properties.id', 'tasks.taskable_id')->whereRaw('tasks.taskable_type = ?', [(new Property)->getMorphClass()])->limit(1), $direction)
                    ->orderBy(Deal::query()->select('title')->whereColumn('deals.id', 'tasks.taskable_id')->whereRaw('tasks.taskable_type = ?', [(new Deal)->getMorphClass()])->limit(1), $direction)
                    ->orderBy(SurplusCase::query()->select('case_number')->whereColumn('surplus_cases.id', 'tasks.taskable_id')->whereRaw('tasks.taskable_type = ?', [(new SurplusCase)->getMorphClass()])->limit(1), $direction)
                    ->orderBy(PreAuctionAcquisition::query()->select('case_number')->whereColumn('pre_auction_acquisitions.id', 'tasks.taskable_id')->whereRaw('tasks.taskable_type = ?', [(new PreAuctionAcquisition)->getMorphClass()])->limit(1), $direction),
                'assigned_to' => $tasks
                    ->orderByRaw('assigned_user_id is null')
                    ->orderBy(User::query()->select('name')->whereColumn('users.id', 'tasks.assigned_user_id')->limit(1), $direction),
                'due' => $tasks->orderByRaw('due_at is null')->orderBy('due_at', $direction),
                'priority' => $tasks->orderByRaw("case priority when 'low' then 1 when 'normal' then 2 when 'high' then 3 when 'urgent' then 4 else 0 end {$direction}"),
                'status' => $tasks->orderByRaw("case status when 'pending' then 1 when 'in_progress' then 2 when 'completed' then 3 when 'cancelled' then 4 else 0 end {$direction}"),
            };
            $tasks->orderBy('id', $direction);
        } else {
            $tasks
                ->orderByRaw("case when status = 'completed' then 1 when status = 'cancelled' then 2 else 0 end")
                ->orderByRaw('due_at is null')
                ->orderBy('due_at')
                ->latest('id');
        }

        $tasks = $tasks->paginate(25)
            ->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'metrics' => $metrics,
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Task::class);

        return view('tasks.create', $this->formData($request));
    }

    public function store(StoreTaskRequest $request, TaskService $service): RedirectResponse
    {
        $task = $service->create($request->validated(), $request->user());

        return redirect()->route('tasks.show', $task)->with('success', 'Task created.');
    }

    public function show(Task $task): View
    {
        Gate::authorize('view', $task);
        $task->load(['assignedUser:id,name,email', 'taskable', 'recurrenceParent:id,title,due_at']);

        return view('tasks.show', compact('task'));
    }

    public function edit(Request $request, Task $task): View
    {
        Gate::authorize('update', $task);
        $task->load('taskable');

        return view('tasks.edit', ['task' => $task, ...$this->formData($request)]);
    }

    public function update(UpdateTaskRequest $request, Task $task, TaskService $service): RedirectResponse
    {
        $service->update($task, $request->validated(), $request->user());

        return redirect()->route('tasks.show', $task)->with('success', 'Task updated.');
    }

    public function complete(Request $request, Task $task, TaskService $service): RedirectResponse
    {
        Gate::authorize('update', $task);
        $nextTask = $service->complete($task, $request->user());

        return back()->with('success', $nextTask
            ? 'Task completed and the next recurring task was created.'
            : 'Task completed.');
    }

    public function bulkUpdateStatus(BulkUpdateTaskStatusRequest $request, TaskService $service): RedirectResponse
    {
        $data = $request->validated();
        $tasks = Task::query()->whereKey($data['task_ids'])->get();

        foreach ($tasks as $task) {
            Gate::authorize('update', $task);
        }

        $status = TaskStatus::from($data['status']);
        $updated = $service->bulkUpdateStatus($tasks->modelKeys(), $status, $request->user());

        return back()->with('success', $updated.' '.str('task')->plural($updated).' changed to '.$status->label().'.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);
        $task->updateQuietly(['updated_by' => $request->user()->id]);
        $task->delete();

        return redirect()->route('tasks.index')->with('success', 'Task archived.');
    }

    private function formData(Request $request): array
    {
        $template = null;
        if ($request->filled('template')) {
            $template = TaskTemplate::query()->where('is_active', true)->find($request->integer('template'));
        }

        $dueAt = $template?->due_in_days !== null ? now()->addDays($template->due_in_days) : null;
        $defaults = $template ? [
            'template_id' => $template->id,
            'title' => $template->title,
            'description' => $template->description,
            'priority' => $template->priority->value,
            'due_at' => $dueAt?->format('Y-m-d\TH:i'),
            'reminder_at' => $dueAt && $template->reminder_lead_minutes !== null
                ? $dueAt->copy()->subMinutes($template->reminder_lead_minutes)->format('Y-m-d\TH:i')
                : null,
            'recurrence_frequency' => $template->recurrence_frequency?->value,
            'recurrence_interval' => $template->recurrence_interval,
        ] : [];

        return [
            'defaults' => $defaults,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'recurrences' => TaskRecurrence::cases(),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()
                ->when(! $request->user()->canViewSurplusCases(), fn ($query) => $query->where('type', '!=', ContactType::Surplus->value))
                ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', ContactType::PreTaxAuctions->value))
                ->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
            'properties' => Property::query()->orderBy('state')->orderBy('city')->orderBy('address')->get(['id', 'address', 'city', 'state', 'postal_code']),
            'deals' => Deal::query()
                ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', DealType::PreTaxAuctionAcquisition->value))
                ->orderByDesc('id')->get(['id', 'deal_number', 'title', 'status']),
            'surplusCases' => $request->user()->canViewSurplusCases() ? SurplusCase::query()->orderByPipelineStatus()->orderByDesc('id')->get(['id', 'case_number', 'claimant_contact_id', 'status']) : collect(),
            'preAuctionCases' => $request->user()->canViewPreAuctionAcquisitions() ? PreAuctionAcquisition::query()->orderByPipelineStatus()->orderByDesc('id')->get(['id', 'case_number', 'owner_contact_id', 'status']) : collect(),
            'templates' => TaskTemplate::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
