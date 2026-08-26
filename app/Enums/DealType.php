<?php

namespace App\Enums;

enum DealType: string
{
    case Acquisition = 'acquisition';
    case Disposition = 'disposition';
    case SurplusRecovery = 'surplus_recovery';
    case PreTaxAuctionAcquisition = 'pre_tax_auction_acquisition';
    case Rental = 'rental';

    public function label(): string
    {
        return match ($this) {
            self::PreTaxAuctionAcquisition => 'PreTax Auction Acquisition',
            default => str($this->value)->headline()->toString(),
        };
    }
}
