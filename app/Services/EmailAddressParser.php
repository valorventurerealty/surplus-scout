<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class EmailAddressParser
{
    public function parse(?string $value, string $field, bool $required = false): array
    {
        $addresses = collect(preg_split('/[,;\r\n]+/', (string) $value))
            ->map(fn (string $address): string => strtolower(trim($address)))
            ->filter()->unique()->values()->all();
        if ($required && $addresses === []) {
            throw ValidationException::withMessages([$field => 'At least one recipient is required.']);
        }
        if (count($addresses) > config('email.max_recipients')) {
            throw ValidationException::withMessages([$field => 'Too many recipients. The limit is '.config('email.max_recipients').'.']);
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw ValidationException::withMessages([$field => "{$address} is not a valid email address."]);
            }
        }
        return $addresses;
    }
}
