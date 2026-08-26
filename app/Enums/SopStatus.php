<?php

namespace App\Enums;

enum SopStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
