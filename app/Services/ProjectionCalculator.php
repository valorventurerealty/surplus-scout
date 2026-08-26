<?php

namespace App\Services;

use App\Enums\ProjectionCategory;
use App\Models\ProjectionScenario;

class ProjectionCalculator
{
    public const VVR_PERCENTAGE = 20;
    public const CONTACT_ONE_PERCENTAGE = 40;
    public const CONTACT_TWO_PERCENTAGE = 40;

    /** @return array{total: int, vvr: int, contact_one: int, contact_two: int} */
    public function splitCents(int $totalCents): array
    {
        $vvr = (int) round($totalCents * self::VVR_PERCENTAGE / 100);
        $contactOne = (int) round($totalCents * self::CONTACT_ONE_PERCENTAGE / 100);

        return [
            'total' => $totalCents,
            'vvr' => $vvr,
            'contact_one' => $contactOne,
            'contact_two' => $totalCents - $vvr - $contactOne,
        ];
    }

    /** @return array<string, mixed> */
    public function summarize(ProjectionScenario $scenario): array
    {
        $scenario->loadMissing(['assumptions', 'entries', 'contactOne', 'contactTwo']);
        $averages = $scenario->assumptions->mapWithKeys(
            fn ($assumption): array => [$assumption->category->value => (int) round((float) $assumption->average_net_profit * 100)]
        );
        $empty = fn (): array => [
            'units' => 0,
            'total' => 0,
            'vvr' => 0,
            'contact_one' => 0,
            'contact_two' => 0,
        ];
        $grand = $empty();
        $years = [];
        $categories = [];

        foreach (ProjectionCategory::cases() as $category) {
            $categories[$category->value] = [
                'category' => $category,
                'average_net_profit' => $averages->get($category->value, (int) round($category->defaultAverageNetProfit() * 100)),
                ...$empty(),
            ];
        }

        foreach ($scenario->years() as $year) {
            $years[$year] = ['year' => $year, 'months' => [], 'categories' => [], ...$empty()];
            foreach (range(1, 12) as $month) {
                $years[$year]['months'][$month] = ['month' => $month, 'categories' => [], ...$empty()];
            }
            foreach (ProjectionCategory::cases() as $category) {
                $years[$year]['categories'][$category->value] = ['category' => $category, ...$empty()];
            }
        }

        foreach ($scenario->entries as $entry) {
            if (! isset($years[$entry->year]['months'][$entry->month])) {
                continue;
            }
            $categoryKey = $entry->category->value;
            $units = $entry->projected_units;
            $split = $this->splitCents($units * $averages->get(
                $categoryKey,
                (int) round($entry->category->defaultAverageNetProfit() * 100),
            ));
            $result = ['units' => $units, ...$split];
            $years[$entry->year]['months'][$entry->month]['categories'][$categoryKey] = [
                'category' => $entry->category,
                ...$result,
            ];
            $this->add($years[$entry->year]['months'][$entry->month], $result);
            $this->add($years[$entry->year]['categories'][$categoryKey], $result);
            $this->add($years[$entry->year], $result);
            $this->add($categories[$categoryKey], $result);
            $this->add($grand, $result);
        }

        return compact('grand', 'years', 'categories');
    }

    /** @param array<string, int> $target @param array<string, int> $amounts */
    private function add(array &$target, array $amounts): void
    {
        foreach (['units', 'total', 'vvr', 'contact_one', 'contact_two'] as $field) {
            $target[$field] += $amounts[$field];
        }
    }
}
