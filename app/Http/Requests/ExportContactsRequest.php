<?php

namespace App\Http\Requests;

use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Contact::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['search' => trim((string) $this->input('search'))]);
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['selected', 'filtered'])],
            'contact_ids' => [Rule::requiredIf($this->input('mode') === 'selected'), 'array', 'min:1', 'max:1000'],
            'contact_ids.*' => ['integer', 'distinct'],
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(ContactType::class)],
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'sort' => ['nullable', Rule::in(['name', 'company', 'email', 'associated_tasks', 'next_follow_up'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
