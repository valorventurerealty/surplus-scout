<?php

namespace App\Enums;

enum DealStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case UnderContract = 'under_contract';
    case DueDiligence = 'due_diligence';
    case Closing = 'closing';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string { return str($this->value)->headline()->toString(); }
    public function isOpen(): bool { return ! in_array($this, [self::Closed, self::Cancelled], true); }
}
