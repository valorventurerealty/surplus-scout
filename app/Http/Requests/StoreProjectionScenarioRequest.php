<?php

namespace App\Http\Requests;

use App\Models\ProjectionScenario;

class StoreProjectionScenarioRequest extends ProjectionScenarioRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProjectionScenario::class) ?? false;
    }
}
