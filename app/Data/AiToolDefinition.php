<?php

namespace App\Data;

use App\Enums\UserRole;

final readonly class AiToolDefinition
{
    /** @param array<int, UserRole> $allowedRoles */
    public function __construct(
        public string $name,
        public string $description,
        public array $inputSchema,
        public array $allowedRoles,
        public int $riskLevel,
        public bool $requiresApproval,
        public bool $enabled = true,
    ) {}

    public function allows(UserRole $role): bool
    {
        return $this->enabled && in_array($role, $this->allowedRoles, true);
    }
}
