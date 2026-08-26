<?php

namespace App\Enums;

enum PropertyChecklistKey: string
{
    case MaxBid = 'max_bid';
    case PropertyCard = 'property_card';
    case AcquisitionDeed = 'acquisition_deed';
    case QuietTitleFinalJudgment = 'quiet_title_final_judgment';

    public function label(): string
    {
        return match ($this) {
            self::MaxBid => 'Max bid',
            self::PropertyCard => 'Property card',
            self::AcquisitionDeed => 'Acquisition deed',
            self::QuietTitleFinalJudgment => 'Quiet title / final judgment',
        };
    }
}
