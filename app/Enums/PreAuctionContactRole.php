<?php

namespace App\Enums;

enum PreAuctionContactRole: string
{
    case Owner = 'owner';
    case CoOwner = 'co_owner';
    case Spouse = 'spouse';
    case Relative = 'relative';
    case Representative = 'representative';
    case Heir = 'heir';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
