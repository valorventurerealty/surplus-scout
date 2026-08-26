<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();
        $task = Task::query()->find(data_get($record->data, 'task_id'));

        return $task
            ? redirect()->route('tasks.show', $task)
            : redirect()->route('tasks.index');
    }
}
