<?php

namespace App\Policies;

use App\Enums\OutboundEmailStatus;
use App\Models\OutboundEmail;
use App\Models\User;

class OutboundEmailPolicy
{
    public function before(User $user): ?bool { return $user->is_active ? null : false; }
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, OutboundEmail $email): bool
    {
        if ($email->user_id !== $user->id && ! $user->canViewAllOutboundEmails()) return false;
        if ($email->primaryContact && ! $user->can('view', $email->primaryContact)) return false;
        return ! $email->related || $user->can('view', $email->related);
    }
    public function create(User $user): bool { return $user->canSendEmail(); }
    public function update(User $user, OutboundEmail $email): bool { return $email->user_id === $user->id && $this->view($user, $email) && $user->canSendEmail() && $email->status === OutboundEmailStatus::Draft; }
    public function send(User $user, OutboundEmail $email): bool { return $this->update($user, $email); }
    public function cancel(User $user, OutboundEmail $email): bool { return $this->view($user, $email) && $email->status === OutboundEmailStatus::Queued; }
    public function retry(User $user, OutboundEmail $email): bool { return $email->user_id === $user->id && $this->view($user, $email) && $user->canSendEmail() && $email->status === OutboundEmailStatus::Failed; }
    public function delete(User $user, OutboundEmail $email): bool { return $email->user_id === $user->id && $this->view($user, $email) && $email->status === OutboundEmailStatus::Draft; }
}
