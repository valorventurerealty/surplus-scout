<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreContactTaskRequest;
use App\Models\Contact;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContactTaskController extends Controller
{
    public function store(StoreContactTaskRequest $request, Contact $contact): RedirectResponse
    {
        $contact->tasks()->create($request->safe()->merge([
            'status' => TaskStatus::Pending,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ])->all());

        return back()->with('success', 'Task created.');
    }

    public function complete(Request $request, Contact $contact, Task $task, TaskService $service): RedirectResponse
    {
        Gate::authorize('update', $task);

        $service->complete($task, $request->user());

        return back()->with('success', 'Task completed.');
    }

    public function destroy(Request $request, Contact $contact, Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);
        $task->updateQuietly(['updated_by' => $request->user()->id]);
        $task->delete();

        return back()->with('success', 'Task archived.');
    }
}
