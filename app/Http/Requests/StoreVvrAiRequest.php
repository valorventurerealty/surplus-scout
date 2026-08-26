<?php

namespace App\Http\Requests;

use App\Models\AiConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreVvrAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AiConversation::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['prompt' => trim((string) $this->input('prompt'))]);
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:3', 'max:5000'],
            'document' => [
                Rule::prohibitedIf(! $this->user()?->canViewPropertySourceDocuments()),
                'nullable', File::types(config('ai.allowed_file_types'))->max((int) config('ai.file_upload_limit_kb')),
            ],
            'acknowledge_external_processing' => ['required', 'accepted'],
        ];
    }
}
