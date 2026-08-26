<?php

namespace App\Contracts;

use App\Data\AiDocumentInput;
use App\Data\AiStructuredResponse;

interface AiProviderInterface
{
    public function isConfigured(): bool;

    public function generateStructured(
        array $schema,
        string $systemPrompt,
        string $userPrompt,
    ): AiStructuredResponse;

    public function generateStructuredFromDocument(
        AiDocumentInput $document,
        array $schema,
        string $systemPrompt,
        string $userPrompt,
    ): AiStructuredResponse;
}
