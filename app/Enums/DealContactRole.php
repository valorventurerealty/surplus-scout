<?php

namespace App\Enums;

enum DealContactRole: string
{
    case Seller = 'seller';
    case Buyer = 'buyer';
    case Investor = 'investor';
    case Builder = 'builder';
    case Attorney = 'attorney';
    case Realtor = 'realtor';
    case TitleCompany = 'title_company';
    case Other = 'other';

    public function label(): string { return str($this->value)->headline()->toString(); }
}
