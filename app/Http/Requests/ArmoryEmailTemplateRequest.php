<?php

namespace App\Http\Requests;

use App\Enums\ArmoryEmailTemplateCategory;
use App\Enums\ArmoryEmailTemplateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ArmoryEmailTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'category' => $this->input('category') ?: ArmoryEmailTemplateCategory::Other->value,
            'status' => $this->input('status') ?: ArmoryEmailTemplateStatus::Draft->value,
            'version_label' => trim((string) ($this->input('version_label') ?: '1.0')),
            'subject' => trim((string) $this->input('subject')),
        ]);
    }

    public function rules(): array
    {
        $extensions = implode(',', config('email.allowed_extensions'));
        $template = $this->route('emailTemplate');

        return [
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::enum(ArmoryEmailTemplateCategory::class)],
            'status' => ['required', Rule::enum(ArmoryEmailTemplateStatus::class)],
            'version_label' => ['required', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:5000'],
            'subject' => ['required', 'string', 'max:255'],
            'body_text' => ['required', 'string', 'max:500000'],
            'attachments' => ['nullable', 'array', 'max:'.config('email.max_attachments')],
            'attachments.*' => ['file', 'max:'.config('email.attachment_max_kb'), 'mimes:'.$extensions],
            'remove_attachments' => ['nullable', 'array', 'max:'.config('email.max_attachments')],
            'remove_attachments.*' => ['integer', Rule::exists('armory_email_template_attachments', 'id')->when(
                $template instanceof \App\Models\ArmoryEmailTemplate,
                fn ($rule) => $rule->where('armory_email_template_id', $template->id)
            )],
        ];
    }
}
