<?php

namespace App\Policies;

use App\Models\PreAuctionAcquisition;
use App\Models\User;

class PreAuctionAcquisitionPolicy
{
    public function before(User $user): ?bool { return $user->is_active ? null : false; }
    public function viewAny(User $user): bool { return $user->canViewPreAuctionAcquisitions(); }
    public function view(User $user, PreAuctionAcquisition $case): bool { return $user->canViewPreAuctionAcquisitions(); }
    public function create(User $user): bool { return $user->canManagePreAuctionAcquisitions(); }
    public function update(User $user, PreAuctionAcquisition $case): bool { return $user->canManagePreAuctionAcquisitions(); }
    public function delete(User $user, PreAuctionAcquisition $case): bool { return $user->role->canArchivePreAuctionAcquisitions(); }
    public function viewFinancials(User $user, PreAuctionAcquisition $case): bool { return $user->canViewPreAuctionFinancials(); }
    public function viewDocuments(User $user, PreAuctionAcquisition $case): bool { return $user->canViewPropertySourceDocuments(); }
}
