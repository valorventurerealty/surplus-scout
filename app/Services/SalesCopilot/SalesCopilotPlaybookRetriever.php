<?php

namespace App\Services\SalesCopilot;

use App\Models\SalesCopilotPlaybook;
use Illuminate\Support\Str;

class SalesCopilotPlaybookRetriever
{
    public function retrieve(string $statement, string $classification, ?string $stage = null): ?SalesCopilotPlaybook
    {
        $needle = $this->normalize($statement);
        $ranked = SalesCopilotPlaybook::query()->where('active', true)->get()->map(function (SalesCopilotPlaybook $playbook) use ($needle, $classification, $stage): array {
            $score = ($playbook->owner_authored ? 1000 : 0) + ($playbook->vvr_approved ? 500 : 0) + $playbook->priority;
            foreach ($playbook->trigger_phrases as $phrase) {
                $normalized = $this->normalize($phrase);
                if ($normalized !== '' && str_contains($needle, $normalized)) $score += 2000;
                else $score += count(array_intersect(explode(' ', $needle), explode(' ', $normalized))) * 25;
            }
            if ($stage && $playbook->stage === $stage) $score += 20;
            if ($classification === 'legitimacy_concern' && $playbook->slug === 'scam-legitimacy') $score += 1000;
            if ($classification === 'price_fee_concern' && in_array($playbook->slug, ['get-back-to-you','competitor-comparison'], true)) $score += 100;
            return [$playbook, $score];
        })->sortByDesc(fn (array $item) => $item[1]);
        $first = $ranked->first();
        return is_array($first) && $first[1] >= 1800 ? $first[0] : null;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', Str::lower(Str::ascii($value))) ?? '') ?? '');
    }
}
