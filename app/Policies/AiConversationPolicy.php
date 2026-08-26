<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\User;

class AiConversationPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->canUseVvrAi();
    }

    public function create(User $user): bool
    {
        return $user->canUseVvrAi();
    }

    public function view(User $user, AiConversation $conversation): bool
    {
        return $user->canUseVvrAi() && $conversation->user_id === $user->id;
    }
}
