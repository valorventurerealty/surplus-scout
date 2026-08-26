<?php

namespace App\Contracts;

use App\Models\AiActionPlan;
use App\Models\User;

interface ApprovalServiceInterface
{
    public function approve(AiActionPlan $plan, User $user): AiActionPlan;

    public function reject(AiActionPlan $plan, User $user, ?string $reason): AiActionPlan;
}
