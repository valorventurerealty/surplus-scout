<?php

namespace App\Http\Requests;

class UpdateSurplusCaseRequest extends SurplusCaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('surplus')) ?? false;
    }
}
