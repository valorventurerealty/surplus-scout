<?php

namespace App\Enums;

enum ArmorySessionStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
