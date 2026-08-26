<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class TaskTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'title' => trim((string) $this->input('title')),
            'is_active' => $this->boolean('is_active'),
            'recurrence_frequency' => filled($this->input('recurrence_frequency'))
                ? $this->input('recurrence_frequency')
                : null,
            'recurrence_interval' => $this->integer('recurrence_interval', 1),
        ]);
    }

    public function rules(): array
    {
        $template = $this->route('template');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('task_templates', 'name')->ignore($template)],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_in_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'reminder_lead_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'recurrence_frequency' => ['nullable', Rule::enum(TaskRecurrence::class)],
            'recurrence_interval' => ['required_with:recurrence_frequency', 'integer', 'min:1', 'max:52'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('reminder_lead_minutes') && ! $this->filled('due_in_days')) {
                $validator->errors()->add('reminder_lead_minutes', 'A reminder lead time requires a due-in-days value.');
            }

            if ($this->filled('recurrence_frequency') && ! $this->filled('due_in_days')) {
                $validator->errors()->add('recurrence_frequency', 'A recurring template requires a due-in-days value.');
            }
        }];
    }
}
