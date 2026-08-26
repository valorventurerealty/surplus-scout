<?php

namespace App\Services;

use App\Models\ArmorySession;

class ArmoryScriptVariableRenderer
{
    public function render(?string $text, ArmorySession $session): string
    {
        return strtr((string) $text, [
            '{{contact_name}}' => $session->contact?->full_name ?? 'the contact',
            '{{property_address}}' => $session->property?->full_address ?? 'the property',
            '{{user_name}}' => $session->user->name,
            '{{caller_name}}' => $session->caller_name ?: ($session->contact?->full_name ?? 'the caller'),
        ]);
    }
}
