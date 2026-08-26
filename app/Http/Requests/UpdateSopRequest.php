<?php

namespace App\Http\Requests;

class UpdateSopRequest extends SopRequest
{
    public function authorize(): bool { return $this->user()?->can('update', $this->route('sop')) ?? false; }
}
