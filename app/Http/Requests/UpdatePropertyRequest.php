<?php

namespace App\Http\Requests;

class UpdatePropertyRequest extends PropertyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('property')) ?? false;
    }
}
