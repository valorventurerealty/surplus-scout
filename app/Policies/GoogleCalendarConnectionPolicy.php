<?php

namespace App\Policies;

use App\Models\GoogleCalendarConnection;
use App\Models\User;

class GoogleCalendarConnectionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->canManageIntegrations();
    }

    public function view(User $user, GoogleCalendarConnection $connection): bool
    {
        return $user->canManageIntegrations();
    }

    public function create(User $user): bool
    {
        return $user->canManageIntegrations();
    }

    public function update(User $user, GoogleCalendarConnection $connection): bool
    {
        return $user->canManageIntegrations();
    }

    public function delete(User $user, GoogleCalendarConnection $connection): bool
    {
        return $user->canManageIntegrations();
    }
}
