<?php

namespace App\Data;

use App\Enums\ContactType;

final readonly class ContactExtractionResult
{
    private const FORM_FIELDS = ['first_name', 'last_name', 'company', 'email', 'phone', 'type', 'notes'];

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

    public function formValues(): array
    {
        $values = [];
        foreach ($this->fields as $field) {
            $name = $field['field'];
            $value = trim((string) ($field['value'] ?? ''));
            if (! in_array($name, self::FORM_FIELDS, true) || $value === '') {
                continue;
            }

            if ($name === 'email') {
                $value = strtolower($value);
            }

            if ($name === 'type') {
                $value = collect(ContactType::cases())->first(fn ($case) => $case->value === strtolower($value))?->value;
            }

            if ($value !== null && $value !== '') {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    public function toArray(): array
    {
        return ['fields' => $this->fields, 'missing_fields' => $this->missingFields, 'warnings' => $this->warnings];
    }
}
