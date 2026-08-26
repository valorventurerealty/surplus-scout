<?php

namespace App\Policies;

use App\Models\Sop;
use App\Models\User;

class SopPolicy
{
    public function before(User $user): ?bool { return $user->is_active ? null : false; }
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Sop $sop): bool { return true; }
    public function create(User $user): bool { return $user->canManageSops(); }
    public function update(User $user, Sop $sop): bool { return $user->canManageSops(); }
    public function delete(User $user, Sop $sop): bool { return $user->role->canArchiveSops(); }
}
