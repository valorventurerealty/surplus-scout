<?php

namespace App\Http\Requests;

use App\Models\Property;

class StorePropertyRequest extends PropertyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
    }
}
