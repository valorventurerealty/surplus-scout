<?php

namespace App\Policies;

use App\Enums\DealType;
use App\Models\Deal;
use App\Models\User;

class DealPolicy
{
    public function before(User $user): ?bool { return $user->is_active ? null : false; }
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Deal $deal): bool { return $deal->type !== DealType::PreTaxAuctionAcquisition || $user->canViewPreAuctionAcquisitions(); }
    public function create(User $user): bool { return $user->canManageDeals(); }
    public function update(User $user, Deal $deal): bool { return $user->canManageDeals() && $this->view($user, $deal); }
    public function delete(User $user, Deal $deal): bool { return $user->role->canArchiveDeals() && $this->view($user, $deal); }
    public function viewFinancials(User $user, Deal $deal): bool { return $user->canViewPropertyFinancials(); }
    public function viewDocuments(User $user, Deal $deal): bool { return $user->canViewPropertySourceDocuments(); }
}
