<?php

namespace App\Enums;

enum TaskRecurrence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
