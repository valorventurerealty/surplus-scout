<?php

namespace App\Http\Requests;

use App\Enums\SurplusCaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BulkUpdateSurplusStageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('county')) {
            $county = preg_replace('/\s+county$/i', '', trim((string) $this->input('county'))) ?? '';
            $this->merge(['county' => Str::squish($county)]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->canManageSurplusCases() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operation' => ['required', Rule::in(['stage', 'county'])],
            'case_ids' => ['required', 'array', 'min:1', 'max:200'],
            'case_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('surplus_cases', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['nullable', 'required_if:operation,stage', Rule::enum(SurplusCaseStatus::class)],
            'county' => ['nullable', 'required_if:operation,county', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'case_ids.required' => 'Select at least one Surplus case.',
            'case_ids.min' => 'Select at least one Surplus case.',
            'case_ids.max' => 'You may update up to 200 Surplus cases at one time.',
            'county.required_if' => 'Enter the county to assign to the selected cases.',
            'status.required_if' => 'Choose the stage to assign to the selected cases.',
        ];
    }
}
