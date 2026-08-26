<?php

namespace App\Enums;

enum PropertyType: string
{
    case Land = 'land';
    case Residential = 'residential';
    case Multifamily = 'multifamily';
    case Commercial = 'commercial';
    case Industrial = 'industrial';
    case Agricultural = 'agricultural';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
