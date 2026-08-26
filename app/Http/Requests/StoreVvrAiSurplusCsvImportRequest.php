<?php

namespace App\Http\Requests;

use App\Models\AiConversation;
use App\Models\Contact;
use App\Models\SurplusCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreVvrAiSurplusCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('create', AiConversation::class)
            && $user->can('create', Contact::class)
            && $user->can('create', SurplusCase::class)
            && $user->canViewSurplusFinancials();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['prompt' => trim((string) $this->input('prompt'))]);
    }

    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:2000'],
            'csv_file' => ['required', File::types(['csv', 'txt'])->max((int) config('ai.file_upload_limit_kb'))],
        ];
    }
}
