<?php

namespace App\Data;

final readonly class SurplusExtractionResult
{
    public function __construct(
        public array $fields,
        public array $missingFields,
        public array $warnings,
        public ?string $responseId = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}

    public static function fromArray(array $data, ?string $responseId = null, ?int $inputTokens = null, ?int $outputTokens = null): self
    {
        return new self(
            array_values($data['fields'] ?? []),
            array_values($data['missing_fields'] ?? []),
            array_values($data['warnings'] ?? []),
            $responseId,
            $inputTokens,
            $outputTokens,
        );
    }

    public function value(string $name): mixed
    {
        $field = collect($this->fields)->firstWhere('field', $name);

        return filled($field['value'] ?? null) ? trim((string) $field['value']) : null;
    }

    public function toArray(): array
    {
        return ['fields' => $this->fields, 'missing_fields' => $this->missingFields, 'warnings' => $this->warnings];
    }
}
