<?php

namespace App\Enums;

enum PhoneInteractionType: string
{
    case Call = 'call';
    case Lead = 'lead';
    case Voicemail = 'voicemail';
    case Message = 'message';
    case Capture = 'capture';
    case VoiceNote = 'voice_note';

    public function label(): string
    {
        return match ($this) {
            self::VoiceNote => 'Voice Note',
            default => str($this->value)->headline()->toString(),
        };
    }
}
