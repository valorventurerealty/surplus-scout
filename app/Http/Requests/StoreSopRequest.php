<?php

namespace App\Http\Requests;

use App\Models\Sop;

class StoreSopRequest extends SopRequest
{
    public function authorize(): bool { return $this->user()?->can('create', Sop::class) ?? false; }
}
