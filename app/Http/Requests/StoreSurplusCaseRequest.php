<?php

namespace App\Http\Requests;

use App\Models\SurplusCase;

class StoreSurplusCaseRequest extends SurplusCaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SurplusCase::class) ?? false;
    }
}
