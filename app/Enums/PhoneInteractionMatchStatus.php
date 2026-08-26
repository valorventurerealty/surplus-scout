<?php

namespace App\Enums;

enum PhoneInteractionMatchStatus: string
{
    case Matched = 'matched';
    case Unmatched = 'unmatched';
    case Conflicting = 'conflicting';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
