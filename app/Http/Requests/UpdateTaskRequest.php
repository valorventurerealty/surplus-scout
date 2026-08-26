<?php

namespace App\Http\Requests;

class UpdateTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('task')) ?? false;
    }
}
