<?php

namespace App\Http\Requests;

use App\Models\TaskTemplate;

class StoreTaskTemplateRequest extends TaskTemplateRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TaskTemplate::class) ?? false;
    }
}
