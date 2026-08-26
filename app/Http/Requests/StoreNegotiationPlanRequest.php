<?php

namespace App\Http\Requests;

use App\Models\NegotiationPlan;

class StoreNegotiationPlanRequest extends NegotiationPlanRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', NegotiationPlan::class) ?? false;
    }
}
