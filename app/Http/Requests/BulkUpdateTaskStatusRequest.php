<?php

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageTasks() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1', 'max:200'],
            'task_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('tasks', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'task_ids.required' => 'Select at least one task.',
            'task_ids.min' => 'Select at least one task.',
            'task_ids.max' => 'You may update up to 200 tasks at one time.',
        ];
    }
}
