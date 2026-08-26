<?php

namespace App\Data;

final readonly class AiStructuredResponse
{
    public function __construct(
        public array $data,
        public ?string $responseId = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}
}
