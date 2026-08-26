<?php

namespace App\Policies;

use App\Enums\ArmorySessionStatus;
use App\Models\ArmorySession;
use App\Models\User;

class ArmorySessionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool { return true; }
    public function view(User $user, ArmorySession $session): bool { return $session->user_id === $user->id || $user->canManageArmory(); }
    public function update(User $user, ArmorySession $session): bool { return $session->user_id === $user->id && $session->status === ArmorySessionStatus::InProgress; }
    public function delete(User $user, ArmorySession $session): bool { return $session->user_id === $user->id || $user->canManageArmory(); }
}
