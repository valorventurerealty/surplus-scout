<?php

namespace App\Enums;

enum SurplusContactRole: string
{
    case Claimant = 'claimant';
    case Relative = 'relative';
    case Heir = 'heir';
    case PersonalRepresentative = 'personal_representative';
    case Attorney = 'attorney';
    case Other = 'other';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
