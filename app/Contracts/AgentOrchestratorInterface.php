<?php

namespace App\Contracts;

use App\Models\AiConversation;
use App\Models\User;

interface AgentOrchestratorInterface
{
    public function prepare(AiConversation $conversation, string $prompt, User $user): array;
}
