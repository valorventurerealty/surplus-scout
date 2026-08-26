<?php

namespace App\Enums;

enum PropertyStatus: string
{
    case Research = 'research';
    case Bidding = 'bidding';
    case Owned = 'owned';
    case ActivelyWorking = 'actively_working';
    case Marketing = 'marketing';
    case UnderContract = 'under_contract';
    case Sold = 'sold';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    /** @return list<string> */
    public static function orderedValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    public function countsTowardPortfolioValue(): bool
    {
        return in_array($this, [
            self::Owned,
            self::ActivelyWorking,
            self::Marketing,
            self::UnderContract,
        ], true);
    }

    /** @return list<string> */
    public static function portfolioValueStatuses(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->countsTowardPortfolioValue()),
        ));
    }

    public function countsTowardFinancialActuals(): bool
    {
        return ! in_array($this, [
            self::Research,
            self::Bidding,
            self::Archived,
        ], true);
    }

    /** @return list<string> */
    public static function financialActualStatuses(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->countsTowardFinancialActuals()),
        ));
    }
}
