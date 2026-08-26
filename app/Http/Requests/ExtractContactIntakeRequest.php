<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ExtractContactIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contact::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'document' => [
                'required',
                File::types(config('ai.allowed_file_types'))->max((int) config('ai.file_upload_limit_kb')),
            ],
        ];
    }
}
