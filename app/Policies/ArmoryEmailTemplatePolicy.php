<?php

namespace App\Policies;

use App\Models\ArmoryEmailTemplate;
use App\Models\User;

class ArmoryEmailTemplatePolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ArmoryEmailTemplate $emailTemplate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageArmory();
    }

    public function update(User $user, ArmoryEmailTemplate $emailTemplate): bool
    {
        return $user->canManageArmory();
    }

    public function delete(User $user, ArmoryEmailTemplate $emailTemplate): bool
    {
        return $user->canManageArmory();
    }
}
