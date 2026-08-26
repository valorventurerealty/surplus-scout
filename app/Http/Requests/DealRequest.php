<?php

namespace App\Http\Requests;

use App\Enums\DealStatus;
use App\Enums\DealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class DealRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['property_id', 'primary_contact_id', 'assigned_user_id', 'contract_date', 'due_diligence_deadline', 'projected_close_date', 'actual_close_date'] as $field) {
            $data[$field] = filled($this->input($field)) ? $this->input($field) : null;
        }
        if ($this->exists('document_drive_url')) {
            $data['document_drive_url'] = filled($this->input('document_drive_url')) ? trim((string) $this->input('document_drive_url')) : null;
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $financialRule = Rule::prohibitedIf(! $this->user()?->canViewPropertyFinancials());
        $documentRule = Rule::prohibitedIf(! $this->user()?->canViewPropertySourceDocuments());
        $money = fn () => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'];

        return [
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::enum(DealType::class)],
            'status' => ['required', Rule::enum(DealStatus::class)],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'primary_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'source' => ['nullable', 'string', 'max:120'],
            'contract_date' => ['nullable', 'date'],
            'due_diligence_deadline' => ['nullable', 'date'],
            'projected_close_date' => ['nullable', 'date'],
            'actual_close_date' => ['nullable', 'date', Rule::requiredIf($this->input('status') === DealStatus::Closed->value)],
            'offer_amount' => $money(),
            'contract_amount' => $money(),
            'earnest_money' => $money(),
            'projected_revenue' => $money(),
            'actual_revenue' => $money(),
            'document_drive_url' => [$documentRule, 'nullable', 'string', 'max:2048', 'url:https'],
            'notes' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('type') === DealType::PreTaxAuctionAcquisition->value && ! $this->user()?->canViewPreAuctionAcquisitions()) {
                $validator->errors()->add('type', 'Your role cannot access PreTax Auction acquisitions.');
            }
        }];
    }
}
