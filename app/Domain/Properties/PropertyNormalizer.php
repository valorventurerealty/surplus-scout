<?php

namespace App\Domain\Properties;

use Illuminate\Support\Str;

class PropertyNormalizer
{
    public function parcelId(?string $value): ?string
    {
        $normalized = Str::upper(preg_replace('/[^a-zA-Z0-9]/', '', (string) $value) ?? '');

        return $normalized !== '' ? $normalized : null;
    }

    public function county(string $value): string
    {
        $normalized = Str::upper(trim(preg_replace('/\s+COUNTY$/i', '', $value) ?? $value));

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    public function address(string $address, string $city, string $state, ?string $postalCode): string
    {
        $value = implode(' ', array_filter([$address, $city, $state, $postalCode]));
        $value = Str::upper(Str::ascii($value));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
