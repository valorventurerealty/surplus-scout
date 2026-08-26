<?php

namespace App\Policies;

use App\Models\TaskTemplate;
use App\Models\User;

class TaskTemplatePolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TaskTemplate $template): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageTaskTemplates();
    }

    public function update(User $user, TaskTemplate $template): bool
    {
        return $user->canManageTaskTemplates();
    }

    public function delete(User $user, TaskTemplate $template): bool
    {
        return $user->canManageTaskTemplates();
    }
}
