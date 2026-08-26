<?php

namespace App\Services;

class FinancialSplitCalculator
{
    public function calculate(null|string|int|float $allInAmount, null|string|int|float $salesPrice): array
    {
        if ($allInAmount === null || $allInAmount === '' || $salesPrice === null || $salesPrice === '') {
            return [
                'profit' => null,
                'distributable_profit' => null,
                'vvr_amount' => null,
                'contact_one_amount' => null,
                'contact_two_amount' => null,
            ];
        }

        $profitCents = $this->toCents($salesPrice) - $this->toCents($allInAmount);
        $distributableCents = max($profitCents, 0);
        $vvrCents = (int) round($distributableCents * 0.20);
        $contactOneCents = (int) round($distributableCents * 0.40);
        $contactTwoCents = $distributableCents - $vvrCents - $contactOneCents;

        return [
            'profit' => $this->fromCents($profitCents),
            'distributable_profit' => $this->fromCents($distributableCents),
            'vvr_amount' => $this->fromCents($vvrCents),
            'contact_one_amount' => $this->fromCents($contactOneCents),
            'contact_two_amount' => $this->fromCents($contactTwoCents),
        ];
    }

    private function toCents(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
