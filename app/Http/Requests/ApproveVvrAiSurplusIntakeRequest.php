<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use App\Models\AiConversation;
use App\Models\Contact;
use App\Models\Property;
use App\Models\SurplusCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ApproveVvrAiSurplusIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof AiConversation
            && $conversation->user_id === $this->user()?->id
            && ($this->user()?->can('create', SurplusCase::class) ?? false)
            && ($this->user()?->can('create', Property::class) ?? false)
            && ($this->user()?->can('create', Contact::class) ?? false)
            && ($this->user()?->canViewPropertySourceDocuments() ?? false);
    }

    protected function prepareForValidation(): void
    {
        foreach (['postal_code', 'mailing_address_line_2', 'mailing_postal_code', 'tax_year'] as $field) {
            if ($this->exists($field) && blank($this->input($field))) {
                $this->merge([$field => null]);
            }
        }
        $this->merge(['state' => strtoupper(trim((string) $this->input('state')))]);
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:999999999999.99'];

        return [
            'intake_token' => ['required', 'uuid', Rule::exists('surplus_intake_files', 'token')->where(fn ($query) => $query
                ->where('user_id', $this->user()?->id)->where('status', 'ready')->where('expires_at', '>', now()))],
            'approve_extracted_data' => ['required', 'accepted'],
            'property_resolution' => ['required', Rule::in(['create', 'use_existing'])],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at'), Rule::requiredIf($this->input('property_resolution') === 'use_existing')],
            'parcel_id' => ['nullable', 'string', 'max:120'], 'county' => ['required_if:property_resolution,create', 'nullable', 'string', 'max:120'],
            'address' => ['required_if:property_resolution,create', 'nullable', 'string', 'max:255'], 'city' => ['required_if:property_resolution,create', 'nullable', 'string', 'max:120'],
            'state' => ['required_if:property_resolution,create', 'nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'postal_code' => ['nullable', 'regex:/^\d{5}(?:-\d{4})?$/'], 'property_type' => ['required_if:property_resolution,create', 'nullable', Rule::enum(PropertyType::class)],
            'acreage' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'legal_description' => ['nullable', 'string', 'max:50000'],
            'contact_resolution' => ['required', Rule::in(['create', 'use_existing'])],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at'), Rule::requiredIf($this->input('contact_resolution') === 'use_existing')],
            'first_name' => ['required_if:contact_resolution,create', 'nullable', 'string', 'max:100'], 'last_name' => ['required_if:contact_resolution,create', 'nullable', 'string', 'max:100'],
            'mailing_address_line_1' => ['nullable', 'string', 'max:255'], 'mailing_address_line_2' => ['nullable', 'string', 'max:255'],
            'mailing_city' => ['nullable', 'string', 'max:120'], 'mailing_state_province' => ['nullable', 'string', 'max:120'],
            'mailing_postal_code' => ['nullable', 'string', 'max:30'], 'mailing_country' => ['nullable', 'string', 'max:100'],
            'surplus_resolution' => ['required', Rule::in(['create', 'use_existing'])],
            'surplus_case_id' => ['nullable', 'integer', Rule::exists('surplus_cases', 'id')->whereNull('deleted_at'), Rule::requiredIf($this->input('surplus_resolution') === 'use_existing')],
            'tax_deed_number' => ['nullable', 'string', 'max:120'],
            'foreclosure_case_number' => ['nullable', 'string', 'max:120'], 'certificate_number' => ['nullable', 'string', 'max:120'],
            'surplus_amount' => $money, 'sale_date' => ['nullable', 'date'], 'claim_deadline' => ['nullable', 'date', 'after_or_equal:sale_date'],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'tax_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 1)],
            'market_value' => $money, 'assessed_value' => $money, 'taxable_value' => $money,
            'prior_year_final_tax' => $money, 'proposed_tax' => $money, 'no_budget_change_tax' => $money,
            'non_ad_valorem_assessments' => $money, 'assessment_classification' => ['nullable', 'string', 'max:255'],
            'create_research_tasks' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $taxFields = ['market_value', 'assessed_value', 'taxable_value', 'prior_year_final_tax', 'proposed_tax', 'no_budget_change_tax', 'non_ad_valorem_assessments', 'assessment_classification'];
            if (collect($taxFields)->contains(fn (string $field): bool => $this->filled($field)) && ! $this->filled('tax_year')) {
                $validator->errors()->add('tax_year', 'Tax year is required when storing tax-history values.');
            }
        }];
    }
}
