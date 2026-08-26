<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebsiteChatConversation;

class WebsiteChatConversationPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WebsiteChatConversation $conversation): bool
    {
        return true;
    }

    public function update(User $user, WebsiteChatConversation $conversation): bool
    {
        return $user->canManageContacts();
    }
}
