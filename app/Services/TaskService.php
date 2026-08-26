<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\PreAuctionAcquisition;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(private readonly TaskRecurrenceCalculator $recurrenceCalculator) {}

    public function create(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor): Task {
            [$attributes, $taskable] = $this->prepare($data);
            $attributes['created_by'] = $actor->id;
            $attributes['updated_by'] = $actor->id;

            if (($attributes['status'] ?? TaskStatus::Pending->value) === TaskStatus::Completed->value) {
                $attributes['completed_at'] = now();
            }

            $task = new Task($attributes);
            if ($taskable) {
                $task->taskable()->associate($taskable);
            }
            $task->save();

            return $task;
        });
    }

    public function update(Task $task, array $data, User $actor): Task
    {
        return DB::transaction(function () use ($task, $data, $actor): Task {
            [$attributes, $taskable] = $this->prepare($data);
            $attributes['updated_by'] = $actor->id;
            $attributes['reminder_sent_at'] = null;
            $attributes['completed_at'] = ($attributes['status'] ?? null) === TaskStatus::Completed->value
                ? ($task->completed_at ?? now())
                : null;

            $task->fill($attributes);
            $taskable ? $task->taskable()->associate($taskable) : $task->taskable()->dissociate();
            $task->save();

            return $task;
        });
    }

    public function complete(Task $task, User $actor): ?Task
    {
        return DB::transaction(function () use ($task, $actor): ?Task {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($task->status === TaskStatus::Completed) {
                return null;
            }

            $task->update([
                'status' => TaskStatus::Completed,
                'completed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            if (! $task->recurrence_frequency || ! $task->due_at) {
                return null;
            }

            $nextDueAt = $this->recurrenceCalculator->next(
                $task->due_at,
                $task->recurrence_frequency,
                $task->recurrence_interval,
            );

            if ($task->recurrence_ends_at && $nextDueAt->isAfter($task->recurrence_ends_at)) {
                return null;
            }

            $rootId = $task->recurrence_parent_id ?: $task->id;
            $key = hash('sha256', $rootId.'|'.$nextDueAt->utc()->format('Y-m-d\TH:i:s\Z'));
            $reminderAt = $task->reminder_at && $task->due_at
                ? $nextDueAt->subSeconds($task->due_at->diffInSeconds($task->reminder_at))
                : null;

            return Task::query()->firstOrCreate(['recurrence_key' => $key], [
                'taskable_type' => $task->taskable_type,
                'taskable_id' => $task->taskable_id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => TaskStatus::Pending,
                'priority' => $task->priority,
                'assigned_user_id' => $task->assigned_user_id,
                'due_at' => $nextDueAt,
                'reminder_at' => $reminderAt,
                'recurrence_frequency' => $task->recurrence_frequency,
                'recurrence_interval' => $task->recurrence_interval,
                'recurrence_ends_at' => $task->recurrence_ends_at,
                'recurrence_parent_id' => $rootId,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    /** @param list<int> $taskIds */
    public function bulkUpdateStatus(array $taskIds, TaskStatus $status, User $actor): int
    {
        return DB::transaction(function () use ($taskIds, $status, $actor): int {
            $tasks = Task::query()
                ->whereKey($taskIds)
                ->lockForUpdate()
                ->get();

            if ($tasks->count() !== count($taskIds)) {
                throw ValidationException::withMessages([
                    'task_ids' => 'One or more selected tasks are no longer available.',
                ]);
            }

            foreach ($tasks as $task) {
                Gate::forUser($actor)->authorize('update', $task);

                if ($status === TaskStatus::Completed) {
                    $this->complete($task, $actor);

                    continue;
                }

                $task->update([
                    'status' => $status,
                    'completed_at' => null,
                    'reminder_sent_at' => null,
                    'updated_by' => $actor->id,
                ]);
            }

            return $tasks->count();
        });
    }

    /** @return array{0: array<string, mixed>, 1: Model|null} */
    private function prepare(array $data): array
    {
        $taskable = $this->resolveTaskable($data['subject'] ?? null);
        $attributes = Arr::except($data, ['subject', 'template_id']);

        if (blank($attributes['recurrence_frequency'] ?? null)) {
            $attributes['recurrence_frequency'] = null;
            $attributes['recurrence_interval'] = 1;
            $attributes['recurrence_ends_at'] = null;
        }

        return [$attributes, $taskable];
    }

    private function resolveTaskable(?string $subject): ?Model
    {
        if (blank($subject)) {
            return null;
        }

        [$type, $id] = explode(':', $subject, 2);

        return match ($type) {
            'contact' => Contact::query()->findOrFail($id),
            'property' => Property::query()->findOrFail($id),
            'deal' => Deal::query()->findOrFail($id),
            'surplus' => SurplusCase::query()->findOrFail($id),
            'pre_auction' => PreAuctionAcquisition::query()->findOrFail($id),
            default => null,
        };
    }
}
