<?php

namespace App\Http\Requests;

use App\Enums\SurplusCaseStatus;
use App\Models\SurplusCase;
use App\Services\SurplusCaseService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class SurplusCaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = ['state' => filled($this->input('state')) ? strtoupper(trim((string) $this->input('state'))) : null];
        foreach (['claimant_contact_id', 'property_id', 'assigned_user_id', 'sale_date', 'claim_deadline', 'agreement_date', 'submitted_at', 'approved_at', 'paid_at'] as $field) {
            $data[$field] = filled($this->input($field)) ? $this->input($field) : null;
        }
        foreach (['parcel_id', 'tax_deed_number', 'foreclosure_case_number', 'certificate_number', 'document_drive_url'] as $field) {
            if ($this->exists($field)) {
                $data[$field] = filled($this->input($field)) ? trim((string) $this->input($field)) : null;
            }
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $financialRule = Rule::prohibitedIf(! $this->user()?->canViewSurplusFinancials());
        $documentRule = Rule::prohibitedIf(! $this->user()?->canViewPropertySourceDocuments());
        $money = fn (): array => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'];
        $requiresClaimant = ! in_array($this->input('status'), [SurplusCaseStatus::Research->value, SurplusCaseStatus::LocateOwner->value], true);

        return [
            'status' => ['required', Rule::enum(SurplusCaseStatus::class)],
            'claimant_contact_id' => [Rule::requiredIf($requiresClaimant), 'nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'source' => ['nullable', 'string', 'max:120'], 'state' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'county' => ['nullable', 'string', 'max:120'], 'parcel_id' => ['nullable', 'string', 'max:120'],
            'tax_deed_number' => ['nullable', 'string', 'max:120'],
            'foreclosure_case_number' => ['nullable', 'string', 'max:120'], 'certificate_number' => ['nullable', 'string', 'max:120'],
            'surplus_amount' => $money(), 'agreed_fee_percentage' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:'.SurplusCaseService::MAX_FEE_PERCENTAGE],
            'recovered_amount' => $money(), 'actual_fee' => $money(),
            'sale_date' => ['nullable', 'date'], 'claim_deadline' => ['nullable', 'date', 'after_or_equal:sale_date'],
            'agreement_date' => ['nullable', 'date'], 'submitted_at' => ['nullable', 'date'],
            'approved_at' => ['nullable', 'date', 'after_or_equal:submitted_at'], 'paid_at' => ['nullable', 'date', 'after_or_equal:approved_at'],
            'document_drive_url' => [$documentRule, 'nullable', 'string', 'max:2048', 'url:https'],
            'notes' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['surplus_amount', 'recovered_amount', 'actual_fee'])) {
                $base = $this->filled('recovered_amount') ? $this->input('recovered_amount') : $this->input('surplus_amount');
                if ($this->filled('actual_fee') && $base !== null) {
                    $maximum = round((float) $base * SurplusCaseService::MAX_FEE_PERCENTAGE) / 100;
                    if ((float) $this->input('actual_fee') > $maximum) {
                        $validator->errors()->add('actual_fee', 'The actual fee may not exceed 12% of the recovered amount or listed surplus.');
                    }
                }
            }

            if ($this->filled('tax_deed_number') && $this->filled('county') && $this->filled('state')) {
                $taxDeedQuery = SurplusCase::query()->where('state', $this->input('state'))->where('county', $this->input('county'))
                    ->where('tax_deed_number', $this->input('tax_deed_number'));
                if ($case = $this->route('surplus')) $taxDeedQuery->whereKeyNot($case->id);
                if ($taxDeedQuery->exists()) {
                    $validator->errors()->add('tax_deed_number', 'A Surplus case already uses this tax deed number in the same county and state.');
                }
            }

            if (! $this->filled('foreclosure_case_number') || ! $this->filled('county') || ! $this->filled('state')) {
                return;
            }
            $query = SurplusCase::query()->where('state', $this->input('state'))->where('county', $this->input('county'))
                ->where('foreclosure_case_number', $this->input('foreclosure_case_number'));
            if ($case = $this->route('surplus')) {
                $query->whereKeyNot($case->id);
            }
            if ($query->exists()) {
                $validator->errors()->add('foreclosure_case_number', 'A Surplus case already uses this foreclosure case number in the same county and state.');
            }
        }];
    }
}
