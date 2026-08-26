<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TaskPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $this->canAccessTaskable($user, $task);
    }

    public function create(User $user, ?Model $taskable = null): bool
    {
        return $user->canManageTasks() && (! $taskable || $user->can('view', $taskable));
    }

    public function update(User $user, Task $task): bool
    {
        return $user->canManageTasks() && $this->canAccessTaskable($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->canManageTasks() && $this->canAccessTaskable($user, $task);
    }

    private function canAccessTaskable(User $user, Task $task): bool
    {
        return ! $task->taskable || $user->can('view', $task->taskable);
    }
}
