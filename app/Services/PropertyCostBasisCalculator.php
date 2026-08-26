<?php

namespace App\Services;

class PropertyCostBasisCalculator
{
    public const COMPONENTS = ['purchase_price', 'taxes', 'attorney_fees', 'realtor_fees', 'other_costs'];

    public function calculate(array $values): ?string
    {
        $components = array_map(fn (string $key): mixed => $values[$key] ?? null, self::COMPONENTS);

        if (count(array_filter($components, fn (mixed $value): bool => $value !== null && $value !== '')) === 0) {
            return null;
        }

        $totalCents = array_reduce($components, fn (int $total, mixed $value): int =>
            $total + (int) round((float) ($value ?: 0) * 100), 0);

        return number_format($totalCents / 100, 2, '.', '');
    }
}
