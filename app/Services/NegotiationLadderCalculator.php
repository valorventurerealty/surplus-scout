<?php

namespace App\Services;

class NegotiationLadderCalculator
{
    public function calculate(
        string|int|float $askingPrice,
        string|int|float $allInAmount,
        null|string|int|float $buyerOffer = null,
        null|string|int|float $counterPercent = null,
    ): array
    {
        $askingCents = $this->toCents($askingPrice);
        $allInCents = $this->toCents($allInAmount);
        $rows = [];

        for ($basisPoints = 10000; $basisPoints >= 5000; $basisPoints -= 250) {
            $rowAskingCents = (int) round($askingCents * ($basisPoints / 10000));
            $profitCents = $rowAskingCents - $allInCents;
            $vvrCents = (int) round($profitCents * .20);
            $investorOneCents = (int) round($profitCents * .40);
            $investorTwoCents = $profitCents - $vvrCents - $investorOneCents;

            $rows[] = [
                'percent' => number_format($basisPoints / 100, 1, '.', ''),
                'asking_price' => $this->fromCents($rowAskingCents),
                'profit' => $this->fromCents($profitCents),
                'vvr_split' => $this->fromCents($vvrCents),
                'investor_one_split' => $this->fromCents($investorOneCents),
                'investor_two_split' => $this->fromCents($investorTwoCents),
            ];
        }

        $offer = null;
        $counterOffer = null;
        if ($buyerOffer !== null && $buyerOffer !== '') {
            $offerCents = $this->toCents($buyerOffer);
            $profitCents = $offerCents - $allInCents;
            $vvrCents = (int) round($profitCents * .20);
            $investorOneCents = (int) round($profitCents * .40);
            $offer = [
                'amount' => $this->fromCents($offerCents),
                'percent_of_ask' => $askingCents > 0 ? number_format(($offerCents / $askingCents) * 100, 1, '.', '') : null,
                'profit' => $this->fromCents($profitCents),
                'vvr_split' => $this->fromCents($vvrCents),
                'investor_one_split' => $this->fromCents($investorOneCents),
                'investor_two_split' => $this->fromCents($profitCents - $vvrCents - $investorOneCents),
            ];

            $closestIndex = 0;
            $closestDistance = PHP_INT_MAX;
            foreach ($rows as $index => $row) {
                $distance = abs($this->toCents($row['asking_price']) - $offerCents);
                if ($distance < $closestDistance) {
                    $closestDistance = $distance;
                    $closestIndex = $index;
                }
            }
            $rows[$closestIndex]['is_closest'] = true;

        }

        if ($counterPercent !== null && $counterPercent !== '') {
            $selectedPercent = number_format((float) $counterPercent, 1, '.', '');
            foreach ($rows as $index => $row) {
                if ($row['percent'] !== $selectedPercent) {
                    continue;
                }

                $rows[$index]['is_counter'] = true;
                $counterOffer = [
                    'amount' => $row['asking_price'],
                    'percent_of_ask' => $row['percent'],
                    'profit' => $row['profit'],
                    'vvr_split' => $row['vvr_split'],
                    'investor_one_split' => $row['investor_one_split'],
                    'investor_two_split' => $row['investor_two_split'],
                    'reason' => 'Selected counter percentage',
                ];
                break;
            }
        }

        return ['rows' => $rows, 'offer' => $offer, 'counter_offer' => $counterOffer];
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
