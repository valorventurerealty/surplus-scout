<?php

namespace App\Enums;

enum ArmoryScriptCategory: string
{
    case Acquisitions = 'acquisitions';
    case Dispositions = 'dispositions';
    case SellerCalls = 'seller_calls';
    case BuyerOutreach = 'buyer_outreach';
    case InvestorRelations = 'investor_relations';
    case SurplusRecovery = 'surplus_recovery';
    case PreTaxAuctions = 'pre_tax_auctions';
    case FollowUp = 'follow_up';
    case Negotiation = 'negotiation';
    case Training = 'training';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PreTaxAuctions => 'PreTax Auctions',
            default => str($this->value)->headline()->toString(),
        };
    }
}
