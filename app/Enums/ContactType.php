<?php

namespace App\Enums;

enum ContactType: string
{
    case Seller = 'seller';
    case Surplus = 'surplus';
    case PreTaxAuctions = 'pre_tax_auctions';
    case Investor = 'investor';
    case Buyer = 'buyer';
    case Builder = 'builder';
    case Developer = 'developer';
    case Agent = 'agent';
    case Realtor = 'realtor';
    case Attorney = 'attorney';
    case Vendor = 'vendor';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PreTaxAuctions => 'PreTax Auctions',
            default => str($this->value)->headline()->toString(),
        };
    }
}
