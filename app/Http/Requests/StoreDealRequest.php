<?php

namespace App\Http\Requests;

use App\Models\Deal;

class StoreDealRequest extends DealRequest
{
    public function authorize(): bool { return $this->user()?->can('create', Deal::class) ?? false; }
}
