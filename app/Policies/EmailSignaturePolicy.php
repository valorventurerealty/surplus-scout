<?php

namespace App\Policies;

use App\Models\EmailSignature;
use App\Models\User;

class EmailSignaturePolicy
{
    public function before(User $user): ?bool { return $user->is_active ? null : false; }
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, EmailSignature $signature): bool { return true; }
    public function create(User $user): bool { return $user->canManageEmailSettings(); }
    public function update(User $user, EmailSignature $signature): bool { return $user->canManageEmailSettings(); }
}
