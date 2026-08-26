<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskRecurrence;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\PreAuctionAcquisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Gate;

abstract class TaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'subject' => filled($this->input('subject')) ? trim((string) $this->input('subject')) : null,
            'assigned_user_id' => filled($this->input('assigned_user_id')) ? $this->input('assigned_user_id') : null,
            'due_at' => filled($this->input('due_at')) ? $this->input('due_at') : null,
            'reminder_at' => filled($this->input('reminder_at')) ? $this->input('reminder_at') : null,
            'recurrence_frequency' => filled($this->input('recurrence_frequency'))
                ? $this->input('recurrence_frequency')
                : null,
            'recurrence_ends_at' => filled($this->input('recurrence_ends_at'))
                ? $this->input('recurrence_ends_at')
                : null,
            'recurrence_interval' => $this->integer('recurrence_interval', 1),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'subject' => ['nullable', 'string', 'regex:/^(contact|property|deal|surplus|pre_auction):[1-9][0-9]*$/'],
            'due_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date', 'before_or_equal:due_at'],
            'recurrence_frequency' => ['nullable', Rule::enum(TaskRecurrence::class)],
            'recurrence_interval' => ['required_with:recurrence_frequency', 'integer', 'min:1', 'max:52'],
            'recurrence_ends_at' => ['nullable', 'date', 'after:due_at'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (filled($this->input('subject')) && ! $validator->errors()->has('subject')) {
                [$type, $id] = explode(':', (string) $this->input('subject'), 2);
                $model = match ($type) {
                    'contact' => Contact::query()->find($id),
                    'property' => Property::query()->find($id),
                    'deal' => Deal::query()->find($id),
                    'surplus' => SurplusCase::query()->find($id),
                    'pre_auction' => PreAuctionAcquisition::query()->find($id),
                    default => null,
                };
                $exists = $model && Gate::forUser($this->user())->allows('view', $model);

                if (! $exists) {
                    $validator->errors()->add('subject', 'The selected CRM record is no longer available.');
                }
            }

            if ($this->filled('reminder_at') && ! $this->filled('due_at')) {
                $validator->errors()->add('reminder_at', 'A reminder requires a due date.');
            }

            if ($this->filled('recurrence_frequency') && ! $this->filled('due_at')) {
                $validator->errors()->add('recurrence_frequency', 'A recurring task requires a due date.');
            }
        }];
    }
}
