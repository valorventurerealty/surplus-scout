<?php

namespace App\Enums;

enum PreAuctionAcquisitionStatus: string
{
    case Research = 'research';
    case OwnerLocated = 'owner_located';
    case Outreach = 'outreach';
    case Negotiating = 'negotiating';
    case PurchaseAgreement = 'purchase_agreement';
    case Closing = 'closing';
    case DeedRecorded = 'deed_recorded';
    case AwaitingAuction = 'awaiting_auction';
    case AuctionCompleted = 'auction_completed';
    case SurplusReview = 'surplus_review';
    case ClaimSubmitted = 'claim_submitted';
    case Paid = 'paid';
    case Closed = 'closed';
    case Disqualified = 'disqualified';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    /** @return list<string> */
    public static function orderedValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Closed, self::Disqualified], true);
    }
}
