<?php

namespace App\Policies;

use App\Models\ProjectionScenario;
use App\Models\User;

class ProjectionScenarioPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->canViewPropertyFinancials();
    }

    public function view(User $user, ProjectionScenario $scenario): bool
    {
        return $user->canViewPropertyFinancials();
    }

    public function create(User $user): bool
    {
        return $user->canManageProjections();
    }

    public function update(User $user, ProjectionScenario $scenario): bool
    {
        return $user->canManageProjections();
    }

    public function delete(User $user, ProjectionScenario $scenario): bool
    {
        return $user->role->canArchiveProjections();
    }
}
