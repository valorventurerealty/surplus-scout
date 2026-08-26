<?php

namespace App\Enums;

enum PreAuctionEntitlementStatus: string
{
    case NotReviewed = 'not_reviewed';
    case NeedsCounsel = 'needs_counsel';
    case PotentiallyEligible = 'potentially_eligible';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
