<?php

namespace App\Http\Requests;

use App\Enums\ArmoryScriptCategory;
use App\Enums\ArmoryScriptStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ArmoryScriptRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => $this->input('category') ?: ArmoryScriptCategory::Other->value,
            'status' => $this->input('status') ?: ArmoryScriptStatus::Draft->value,
            'version_label' => $this->input('version_label') ?: '1.0',
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::enum(ArmoryScriptCategory::class)],
            'status' => ['required', Rule::enum(ArmoryScriptStatus::class)],
            'version_label' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:5000'],
            'content_text' => ['nullable', 'string', 'max:500000'],
            'script_file' => $this->isMethod('post')
                ? ['nullable', 'file', 'max:10240', 'extensions:pdf,doc,docx,txt,md,rtf']
                : ['prohibited'],
        ];
    }
}
