<?php

namespace App\Services;

use App\Models\Property;

class PropertyFinancialCalculator
{
    public const EDITABLE_FIELDS = [
        'purchase_price',
        'taxes',
        'attorney_fees',
        'realtor_fees',
        'other_costs',
        'expected_sales_price',
        'actual_sales_price',
    ];

    public const COMPUTED_FIELDS = [
        'all_in_amount',
        'expected_profit',
        'actual_profit',
    ];

    public function __construct(
        private readonly FinancialSplitCalculator $profitCalculator,
        private readonly PropertyCostBasisCalculator $costBasisCalculator,
    ) {}

    public function hasInput(array $data): bool
    {
        return collect(self::EDITABLE_FIELDS)->contains(
            fn (string $field): bool => array_key_exists($field, $data)
        );
    }

    public function calculate(array $changes, ?Property $property = null): array
    {
        $values = [];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $changes)) {
                $values[$field] = $changes[$field];
            } elseif ($property) {
                $values[$field] = $property->getAttribute($field);
            } else {
                $values[$field] = null;
            }
        }

        $allInAmount = $this->costBasisCalculator->calculate($values);
        $expected = $this->profitCalculator->calculate($allInAmount, $values['expected_sales_price']);
        $actual = $this->profitCalculator->calculate($allInAmount, $values['actual_sales_price']);

        return [
            ...$values,
            'all_in_amount' => $allInAmount,
            'expected_profit' => $expected['profit'],
            'actual_profit' => $actual['profit'],
        ];
    }
}
