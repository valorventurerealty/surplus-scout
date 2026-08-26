<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskRecurrence;
use App\Http\Requests\StoreTaskTemplateRequest;
use App\Http\Requests\UpdateTaskTemplateRequest;
use App\Models\TaskTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaskTemplateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', TaskTemplate::class);

        return view('tasks.templates.index', [
            'templates' => TaskTemplate::query()->orderByDesc('is_active')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', TaskTemplate::class);

        return view('tasks.templates.create', $this->formData());
    }

    public function store(StoreTaskTemplateRequest $request): RedirectResponse
    {
        TaskTemplate::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('task-templates.index')->with('success', 'Task template created.');
    }

    public function edit(TaskTemplate $template): View
    {
        Gate::authorize('update', $template);

        return view('tasks.templates.edit', ['template' => $template, ...$this->formData()]);
    }

    public function update(UpdateTaskTemplateRequest $request, TaskTemplate $template): RedirectResponse
    {
        $template->update([...$request->validated(), 'updated_by' => $request->user()->id]);

        return redirect()->route('task-templates.index')->with('success', 'Task template updated.');
    }

    public function destroy(Request $request, TaskTemplate $template): RedirectResponse
    {
        Gate::authorize('delete', $template);
        $template->update(['is_active' => false, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Task template deactivated.');
    }

    private function formData(): array
    {
        return [
            'priorities' => TaskPriority::cases(),
            'recurrences' => TaskRecurrence::cases(),
        ];
    }
}
