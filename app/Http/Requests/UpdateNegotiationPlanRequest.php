<?php

namespace App\Http\Requests;

class UpdateNegotiationPlanRequest extends NegotiationPlanRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('negotiation')) ?? false;
    }
}
