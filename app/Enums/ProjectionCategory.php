<?php

namespace App\Enums;

enum ProjectionCategory: string
{
    case LandFlip = 'land_flip';
    case PropertyFlip = 'property_flip';
    case Rental = 'rental';
    case Surplus = 'surplus';

    public function label(): string
    {
        return match ($this) {
            self::LandFlip => 'Land Flips',
            self::PropertyFlip => 'Property Flips',
            self::Rental => 'Rental Income',
            self::Surplus => 'Surplus Recovery',
        };
    }

    public function unitLabel(): string
    {
        return match ($this) {
            self::LandFlip, self::PropertyFlip => 'Sales',
            self::Rental => 'Property-months',
            self::Surplus => 'Paid cases',
        };
    }

    public function defaultAverageNetProfit(): float
    {
        return match ($this) {
            self::LandFlip => 10000.00,
            self::PropertyFlip => 40000.00,
            self::Rental, self::Surplus => 1200.00,
        };
    }
}
