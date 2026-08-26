<?php

namespace App\Enums;

enum AuctionCounty: string
{
    case Putnam = 'putnam';
    case Osceola = 'osceola';
    case Marion = 'marion';
    case Polk = 'polk';
    case Brevard = 'brevard';
    case Orange = 'orange';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
