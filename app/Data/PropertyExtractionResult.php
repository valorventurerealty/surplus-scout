<?php

namespace App\Data;

use App\Enums\PropertyType;
use App\Enums\WetlandsStatus;

final readonly class PropertyExtractionResult
{
    private const FORM_FIELDS = [
        'parcel_id', 'county', 'address', 'city', 'state', 'postal_code', 'property_type',
        'acreage', 'zoning', 'flood_zone', 'wetlands_status',
        'road_access', 'purchase_price', 'taxes', 'attorney_fees', 'realtor_fees', 'other_costs',
        'arv', 'wholesale_price', 'investor_price', 'expected_sales_price', 'actual_sales_price',
        'legal_description', 'research_notes',
    ];

    public function __construct(
        public array $fields,
        public array $missingFields,
        public array $warnings,
        public ?string $responseId = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
    ) {}

    public static function fromArray(
        array $data,
        ?string $responseId = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
    ): self {
        return new self(
            fields: array_values($data['fields'] ?? []),
            missingFields: array_values($data['missing_fields'] ?? []),
            warnings: array_values($data['warnings'] ?? []),
            responseId: $responseId,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    public function formValues(bool $includeFinancials): array
    {
        $values = [];
        $financials = [
            'purchase_price', 'taxes', 'attorney_fees', 'realtor_fees', 'other_costs',
            'arv', 'wholesale_price', 'investor_price', 'expected_sales_price', 'actual_sales_price',
        ];

        foreach ($this->fields as $field) {
            $name = $field['field'];
            $value = $field['value'];

            if (! in_array($name, self::FORM_FIELDS, true) || $value === null || $value === '') {
                continue;
            }

            if (! $includeFinancials && in_array($name, $financials, true)) {
                continue;
            }

            $values[$name] = $this->normalize($name, $value);
        }

        foreach (['electricity', 'water', 'sewer', 'gas'] as $utility) {
            $field = collect($this->fields)->firstWhere('field', $utility);
            if (filled($field['value'] ?? null)) {
                $values['utilities'][$utility] = trim((string) $field['value']);
            }
        }

        return array_filter($values, fn ($value) => $value !== null && $value !== '');
    }

    public function toArray(): array
    {
        return [
            'fields' => $this->fields,
            'missing_fields' => $this->missingFields,
            'warnings' => $this->warnings,
        ];
    }

    private function normalize(string $name, mixed $value): mixed
    {
        $value = trim((string) $value);

        if ($name === 'state') {
            $state = strtoupper($value);

            return preg_match('/^[A-Z]{2}$/', $state) ? $state : null;
        }

        if (in_array($name, ['acreage', 'purchase_price', 'taxes', 'attorney_fees', 'realtor_fees', 'other_costs', 'arv', 'wholesale_price', 'investor_price', 'expected_sales_price', 'actual_sales_price'], true)) {
            $numeric = preg_replace('/[^0-9.\-]/', '', $value);

            return is_numeric($numeric) ? $numeric : null;
        }

        if ($name === 'property_type') {
            return collect(PropertyType::cases())->first(fn ($case) => $case->value === strtolower($value))?->value;
        }

        if ($name === 'wetlands_status') {
            return collect(WetlandsStatus::cases())->first(fn ($case) => $case->value === strtolower($value))?->value;
        }

        return $value;
    }
}
