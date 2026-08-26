<?php

namespace App\Domain\Contacts;

use Illuminate\Support\Str;

class ContactNormalizer
{
    public function email(?string $value): ?string
    {
        $email = Str::lower(trim((string) $value));

        return $email !== '' ? $email : null;
    }

    public function phone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return $digits !== '' ? $digits : null;
    }
}
