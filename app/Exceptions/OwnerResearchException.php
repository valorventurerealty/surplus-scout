<?php

namespace App\Exceptions;

use App\Enums\SurplusOwnerResearchStatus;
use RuntimeException;

class OwnerResearchException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly SurplusOwnerResearchStatus $researchStatus,
        public readonly bool $retryable = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
