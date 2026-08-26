<?php

namespace App\Policies;

use App\Models\PhoneInteraction;
use App\Models\User;

class PhoneInteractionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PhoneInteraction $interaction): bool
    {
        if ($interaction->contact) {
            return $user->can('view', $interaction->contact);
        }

        return $user->canViewSurplusCases();
    }

    public function update(User $user, PhoneInteraction $interaction): bool
    {
        return $user->canManageContacts() && $this->view($user, $interaction);
    }
}
