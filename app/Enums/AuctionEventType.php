<?php

namespace App\Enums;

enum AuctionEventType: string
{
    case TaxDeedAuction = 'tax_deed_auction';
    case ForeclosureAuction = 'foreclosure_auction';
    case Meeting = 'meeting';

    public function label(): string
    {
        return match ($this) {
            self::TaxDeedAuction => 'Tax Deed Auction',
            self::ForeclosureAuction => 'Foreclosure Auction',
            self::Meeting => 'Meeting',
        };
    }

    public function isAuction(): bool
    {
        return $this !== self::Meeting;
    }
}
