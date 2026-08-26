<?php

namespace App\Http\Requests;

class UpdateDealRequest extends DealRequest
{
    public function authorize(): bool { return $this->user()?->can('update', $this->route('deal')) ?? false; }
}
