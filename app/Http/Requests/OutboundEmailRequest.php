<?php

namespace App\Http\Requests;

use App\Models\OutboundEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutboundEmailRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        $email = $this->route('outboundEmail');

        return $email instanceof OutboundEmail
            ? route('email.edit', $email)
            : route('email.create');
    }

    protected function prepareForValidation(): void
    {
        $reference = trim((string) $this->input('related_record'));
        if ($reference === '') {
            if ($this->has('related_record')) $this->merge(['related_type' => null, 'related_id' => null]);
            return;
        }

        if (preg_match('/^(contact|property|deal|surplus|pre_auction):(\d+)$/', $reference, $matches) === 1) {
            $this->merge(['related_type' => $matches[1], 'related_id' => (int) $matches[2]]);
        }
    }

    public function authorize(): bool
    {
        $email = $this->route('outboundEmail');
        return $email instanceof OutboundEmail
            ? ($this->user()?->can('update', $email) ?? false)
            : ($this->user()?->can('create', OutboundEmail::class) ?? false);
    }

    public function rules(): array
    {
        $extensions = implode(',', config('email.allowed_extensions'));
        return [
            'to' => ['required', 'string', 'max:4000'],
            'cc' => ['nullable', 'string', 'max:4000'],
            'bcc' => ['nullable', 'string', 'max:4000'],
            'subject' => ['required', 'string', 'max:255'],
            'body_text' => ['required', 'string', 'max:500000'],
            'primary_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'armory_email_template_id' => ['nullable', 'integer', Rule::exists('armory_email_templates', 'id')->whereNull('deleted_at')],
            'email_signature_id' => ['nullable', 'integer', Rule::exists('email_signatures', 'id')->where('is_active', true)],
            'related_record' => ['nullable', 'string', 'max:100', 'regex:/^(contact|property|deal|surplus|pre_auction):\d+$/'],
            'related_type' => ['nullable', Rule::in(['contact', 'property', 'deal', 'surplus', 'pre_auction'])],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
            'attachments' => ['nullable', 'array', 'max:'.config('email.max_attachments')],
            'attachments.*' => ['file', 'max:'.config('email.attachment_max_kb'), 'mimes:'.$extensions],
        ];
    }
}
