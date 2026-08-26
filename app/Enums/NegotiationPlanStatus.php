<?php

namespace App\Enums;

enum NegotiationPlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Accepted = 'accepted';
    case Closed = 'closed';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
