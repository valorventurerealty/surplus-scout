<?php

namespace App\Http\Requests;

class UpdateContactRequest extends ContactRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('contact')) ?? false;
    }
}
