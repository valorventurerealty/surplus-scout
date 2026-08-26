<?php

namespace App\Contracts;

use App\Models\User;

interface ToolExecutorInterface
{
    public function execute(string $toolName, array $arguments, User $user): array;
}
