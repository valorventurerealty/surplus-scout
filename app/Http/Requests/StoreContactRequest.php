<?php

namespace App\Http\Requests;

use App\Models\Contact;

class StoreContactRequest extends ContactRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contact::class) ?? false;
    }
}
