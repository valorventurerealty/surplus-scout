<?php

namespace App\Policies;

use App\Models\ArmoryScript;
use App\Models\User;

class ArmoryScriptPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ArmoryScript $script): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageArmory();
    }

    public function update(User $user, ArmoryScript $script): bool
    {
        return $user->canManageArmory();
    }

    public function delete(User $user, ArmoryScript $script): bool
    {
        return $user->canManageArmory();
    }
}
