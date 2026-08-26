<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalendarEvent $event): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageCalendar();
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return ! $event->isGoogleManaged() && $user->canManageCalendar();
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return ! $event->isGoogleManaged() && $user->role->canArchiveProperties();
    }

    public function viewFinancials(User $user, CalendarEvent $event): bool
    {
        return $user->canViewPropertyFinancials();
    }
}
