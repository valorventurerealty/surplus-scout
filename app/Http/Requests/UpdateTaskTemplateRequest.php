<?php

namespace App\Http\Requests;

class UpdateTaskTemplateRequest extends TaskTemplateRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('template')) ?? false;
    }
}
