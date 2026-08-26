<?php

namespace App\Policies;

use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contact $contact): bool
    {
        return match ($contact->type) {
            ContactType::Surplus => $user->canViewSurplusCases(),
            ContactType::PreTaxAuctions => $user->canViewPreAuctionAcquisitions(),
            default => true,
        };
    }

    public function create(User $user): bool
    {
        return $user->canManageContacts();
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->canManageContacts() && $this->view($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->canManageContacts() && $this->view($user, $contact);
    }

    public function viewSourceDocuments(User $user, Contact $contact): bool
    {
        return $user->canViewPropertyFinancials();
    }
}
