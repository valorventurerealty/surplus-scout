<?php

namespace App\Policies;

use App\Models\NegotiationPlan;
use App\Models\User;

class NegotiationPlanPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, NegotiationPlan $negotiation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageArmory();
    }

    public function update(User $user, NegotiationPlan $negotiation): bool
    {
        return $user->canManageArmory();
    }

    public function delete(User $user, NegotiationPlan $negotiation): bool
    {
        return $user->canManageArmory();
    }
}
