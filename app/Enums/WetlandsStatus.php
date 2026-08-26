<?php

namespace App\Enums;

enum WetlandsStatus: string
{
    case Unknown = 'unknown';
    case NoneFound = 'none_found';
    case Possible = 'possible';
    case Confirmed = 'confirmed';
    case NeedsResearch = 'needs_research';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
