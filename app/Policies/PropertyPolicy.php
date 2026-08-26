<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Property $property): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageProperties();
    }

    public function update(User $user, Property $property): bool
    {
        return $user->canManageProperties();
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->role->canArchiveProperties();
    }

    public function viewFinancials(User $user, Property $property): bool
    {
        return $user->canViewPropertyFinancials();
    }

    public function viewSourceDocuments(User $user, Property $property): bool
    {
        return $user->canViewPropertySourceDocuments();
    }
}
