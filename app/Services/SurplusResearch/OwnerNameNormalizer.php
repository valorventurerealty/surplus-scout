<?php

namespace App\Services\SurplusResearch;

class OwnerNameNormalizer
{
    public function normalize(?string $name): string
    {
        $value = html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtoupper($value);
        $value = preg_replace('/\bJUNIOR\b/u', 'JR', $value) ?? $value;
        $value = preg_replace('/\bSENIOR\b/u', 'SR', $value) ?? $value;
        $value = str_replace('&', ' AND ', $value);
        $value = preg_replace('/[^A-Z0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
