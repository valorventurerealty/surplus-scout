<?php

namespace App\Http\Requests;

use App\Models\Task;

class StoreTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }
}
