<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartSalesCopilotSessionRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->is_active; }
    public function rules(): array
    {
        return [
            'prospect_statement' => ['required','string','max:4000'],
            'salesperson_previous' => ['nullable','string','max:4000'],
            'prospect_name' => ['nullable','string','max:255'],
            'contact_id' => ['nullable','integer','exists:contacts,id'],
            'surplus_case_id' => ['nullable','integer','exists:surplus_cases,id'],
            'call_type' => ['nullable',Rule::in(['inbound','outbound','follow_up','other'])],
            'prospect_relationship' => ['nullable','string','max:80'],
            'current_stage' => ['nullable',Rule::in(['connection','situation','problem_awareness','solution_awareness','consequence','qualifying','transition','presentation','commitment','objection_resolution'])],
            'county' => ['nullable','string','max:120'], 'parcel_id' => ['nullable','string','max:120'],
            'estimated_surplus' => ['nullable','numeric','min:0','max:999999999999.99'],
            'previous_conversation_summary' => ['nullable','string','max:10000'],
            'additional_notes' => ['nullable','string','max:10000'],
        ];
    }
}
