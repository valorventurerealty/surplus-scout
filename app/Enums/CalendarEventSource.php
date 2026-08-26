<?php

namespace App\Enums;

enum CalendarEventSource: string
{
    case Vvr = 'vvr';
    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Vvr => 'VVR Command Center',
            self::Google => 'Google Calendar',
        };
    }
}
