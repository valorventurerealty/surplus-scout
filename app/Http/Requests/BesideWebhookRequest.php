<?php

namespace App\Http\Requests;

use App\Enums\PhoneInteractionDirection;
use App\Enums\PhoneInteractionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BesideWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $summary = $this->firstTextValue(['summary', 'call_summary', 'ai_summary', 'notes', 'call_notes']);
        $transcript = $this->firstTextValue(['transcript', 'call_transcript']);
        $recordingUrl = $this->firstTextValue(['recording_url', 'recording_link', 'recording']);

        $this->merge([
            'event_id' => $this->firstTextValue(['event_id', 'call_id', 'provider_call_id']),
            'direction' => $this->input('direction') ?: PhoneInteractionDirection::Unknown->value,
            'summary' => $summary,
            'transcript' => $transcript,
            'recording_url' => $recordingUrl,
            'action_items' => is_string($this->input('action_items'))
                ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $this->input('action_items')) ?: [])))
                : $this->input('action_items'),
        ]);
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:191'],
            'event_type' => ['required', Rule::enum(PhoneInteractionType::class)],
            'direction' => ['required', Rule::enum(PhoneInteractionDirection::class)],
            'occurred_at' => ['required', 'date'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'caller_name' => ['nullable', 'string', 'max:180'],
            'caller_email' => ['nullable', 'email:rfc', 'max:255'],
            'caller_company' => ['nullable', 'string', 'max:255'],
            'inbox' => ['nullable', 'string', 'max:180'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'summary' => ['nullable', 'string', 'max:100000'],
            'transcript' => ['nullable', 'string', 'max:1000000'],
            'recording_url' => ['nullable', 'url:http,https', 'max:2048'],
            'action_items' => ['nullable', 'array', 'max:100'],
            'action_items.*' => ['string', 'max:1000'],
            'provider_payload' => ['nullable', 'array'],
        ];
    }

    /** @param array<int, string> $keys */
    private function firstTextValue(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->input($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
