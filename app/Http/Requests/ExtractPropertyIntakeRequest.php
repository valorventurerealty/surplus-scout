<?php

namespace App\Http\Requests;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ExtractPropertyIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('create', Property::class) ?? false)
            && ($this->user()?->canViewPropertySourceDocuments() ?? false);
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                File::types(config('ai.allowed_file_types'))->max((int) config('ai.file_upload_limit_kb')),
            ],
            'acknowledge_external_processing' => ['required', 'accepted'],
        ];
    }
}
