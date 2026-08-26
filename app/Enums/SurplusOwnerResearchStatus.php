<?php

namespace App\Enums;

enum SurplusOwnerResearchStatus: string
{
    case Pending = 'pending_owner_research';
    case ResearchingProperty = 'researching_property';
    case ResearchingHistoricalOwner = 'researching_historical_owner';
    case ParcelNotFound = 'parcel_not_found';
    case TrimNoticeNotFound = 'trim_notice_not_found';
    case OwnerMatchUnresolved = 'owner_match_unresolved';
    case ManualReview = 'manual_review';
    case ReadyForSkipTrace = 'ready_for_skip_trace';
    case BusinessResearchNeeded = 'business_research_needed';
    case EstateHeirResearchNeeded = 'estate_heir_research_needed';
    case TrustResearchNeeded = 'trust_research_needed';
    case PropertyAppraiserError = 'property_appraiser_error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Owner Research',
            self::ResearchingProperty => 'Researching Property',
            self::ResearchingHistoricalOwner => 'Researching Historical Owner',
            self::ParcelNotFound => 'Parcel Not Found',
            self::TrimNoticeNotFound => 'TRIM Notice Not Found',
            self::OwnerMatchUnresolved => 'Owner Match Unresolved',
            self::ManualReview => 'Manual Review',
            self::ReadyForSkipTrace => 'Ready for Skip Trace',
            self::BusinessResearchNeeded => 'Business Research Needed',
            self::EstateHeirResearchNeeded => 'Estate / Heir Research Needed',
            self::TrustResearchNeeded => 'Trust Research Needed',
            self::PropertyAppraiserError => 'Property Appraiser Error',
        };
    }

    /** @return list<string> */
    public static function retryableValues(): array
    {
        return [
            self::Pending->value, self::ParcelNotFound->value, self::TrimNoticeNotFound->value,
            self::OwnerMatchUnresolved->value, self::ManualReview->value,
            self::PropertyAppraiserError->value,
        ];
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::ResearchingProperty->value, self::ResearchingHistoricalOwner->value];
    }
}
