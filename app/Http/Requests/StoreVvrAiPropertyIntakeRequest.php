<?php

namespace App\Http\Requests;

use App\Models\AiConversation;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreVvrAiPropertyIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('create', AiConversation::class) ?? false)
            && ($this->user()?->can('create', Property::class) ?? false)
            && ($this->user()?->canViewPropertySourceDocuments() ?? false);
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
                'nullable',
                File::types(config('ai.allowed_file_types'))->max((int) config('ai.file_upload_limit_kb')),
            ],
            'acknowledge_external_processing' => ['required', 'accepted'],
        ];
    }
}
