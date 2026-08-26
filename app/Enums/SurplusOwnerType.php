<?php

namespace App\Enums;

enum SurplusOwnerType: string
{
    case Individual = 'individual';
    case MultipleIndividuals = 'multiple_individuals';
    case Business = 'business';
    case Estate = 'estate';
    case Trust = 'trust';
    case GovernmentAssociation = 'government_association';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::MultipleIndividuals => 'Multiple Individuals',
            self::GovernmentAssociation => 'Government / Association',
            default => str($this->value)->headline()->toString(),
        };
    }
}
