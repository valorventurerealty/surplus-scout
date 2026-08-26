<?php

namespace App\Services\SurplusResearch;

use App\Enums\SurplusOwnerType;

class OwnerClassificationService
{
    public function __construct(private readonly OwnerNameNormalizer $normalizer) {}

    public function classify(string $owner, ?string $coOwner = null): SurplusOwnerType
    {
        $value = $this->normalizer->normalize(trim($owner.' '.($coOwner ?? '')));
        if ($value === '') return SurplusOwnerType::Unknown;

        if (preg_match('/\b(ESTATE OF|EST OF|ESTATE|EST)\b/', $value)) return SurplusOwnerType::Estate;
        if (preg_match('/\b(TRUST|REVOCABLE TRUST|LIVING TRUST|TRUSTEE|TR)\b/', $value)) return SurplusOwnerType::Trust;
        if (preg_match('/\b(COUNTY|CITY OF|STATE OF|UNITED STATES|MUNICIPAL|AUTHORITY|DISTRICT|CDD|ASSOCIATION|HOMEOWNERS|HOA|CHURCH|MINISTRIES|FOUNDATION)\b/', $value)) return SurplusOwnerType::GovernmentAssociation;
        if (preg_match('/\b(LLC|L L C|INC|INCORPORATED|CORP|CORPORATION|COMPANY|CO|LP|L P|LLP|LTD|HOLDINGS|PARTNERSHIP)\b/', $value)) return SurplusOwnerType::Business;
        if (filled($coOwner) || preg_match('/\bAND\b/', $value)) return SurplusOwnerType::MultipleIndividuals;

        $tokens = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) >= 2 && count($tokens) <= 7 && ! preg_match('/\d/', $value)) {
            return SurplusOwnerType::Individual;
        }

        return SurplusOwnerType::Unknown;
    }
}
