<?php

namespace App\Policies;

use App\Models\PropertyFinancialSplit;
use App\Models\User;

class PropertyFinancialSplitPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->canViewPropertyFinancials();
    }

    public function view(User $user, PropertyFinancialSplit $split): bool
    {
        return $user->canViewPropertyFinancials();
    }

    public function create(User $user): bool
    {
        return $user->canViewPropertyFinancials();
    }

    public function update(User $user, PropertyFinancialSplit $split): bool
    {
        return $user->canViewPropertyFinancials();
    }
}
