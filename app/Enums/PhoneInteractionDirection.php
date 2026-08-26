<?php

namespace App\Enums;

enum PhoneInteractionDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Unknown = 'unknown';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
