<?php

namespace App\Services\SurplusResearch;

class OwnerComparisonService
{
    public function __construct(private readonly OwnerNameNormalizer $normalizer) {}

    public function equivalent(?string $left, ?string $right): bool
    {
        $normalizedLeft = $this->normalizer->normalize($left);
        $normalizedRight = $this->normalizer->normalize($right);

        return $normalizedLeft !== '' && hash_equals($normalizedLeft, $normalizedRight);
    }
}
