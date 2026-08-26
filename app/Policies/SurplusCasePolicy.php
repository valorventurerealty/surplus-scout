<?php

namespace App\Policies;

use App\Models\SurplusCase;
use App\Models\User;

class SurplusCasePolicy
{
    public function before(User $user): ?bool { return $user->is_active ? null : false; }
    public function viewAny(User $user): bool { return $user->canViewSurplusCases(); }
    public function view(User $user, SurplusCase $case): bool { return $user->canViewSurplusCases(); }
    public function create(User $user): bool { return $user->canManageSurplusCases(); }
    public function update(User $user, SurplusCase $case): bool { return $user->canManageSurplusCases(); }
    public function delete(User $user, SurplusCase $case): bool { return $user->role->canArchiveSurplusCases(); }
    public function viewFinancials(User $user, SurplusCase $case): bool { return $user->canViewSurplusFinancials(); }
    public function viewDocuments(User $user, SurplusCase $case): bool { return $user->canViewPropertySourceDocuments(); }
}
