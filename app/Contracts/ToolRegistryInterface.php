<?php

namespace App\Contracts;

use App\Data\AiToolDefinition;
use App\Models\User;

interface ToolRegistryInterface
{
    /** @return array<int, AiToolDefinition> */
    public function forUser(User $user): array;

    public function find(string $name): ?AiToolDefinition;
}
