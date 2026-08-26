<?php

namespace App\Enums;

enum ArmoryEmailTemplateCategory: string
{
    case SellerOutreach = 'seller_outreach';
    case BuyerOutreach = 'buyer_outreach';
    case SurplusOutreach = 'surplus_outreach';
    case PreTaxAuctionOutreach = 'pre_tax_auction_outreach';
    case FollowUp = 'follow_up';
    case OffersAndContracts = 'offers_and_contracts';
    case Closing = 'closing';
    case Internal = 'internal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PreTaxAuctionOutreach => 'PreTax Auction Outreach',
            default => str($this->value)->headline()->toString(),
        };
    }
}
