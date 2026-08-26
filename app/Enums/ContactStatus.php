<?php

namespace App\Enums;

enum ContactStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Nurture = 'nurture';
    case DoNotContact = 'do_not_contact';
    case Archived = 'archived';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
