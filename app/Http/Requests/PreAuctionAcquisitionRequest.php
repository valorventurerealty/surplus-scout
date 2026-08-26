<?php

namespace App\Http\Requests;

use App\Enums\PreAuctionAcquisitionStatus;
use App\Enums\PreAuctionEntitlementStatus;
use App\Models\PreAuctionAcquisition;
use App\Domain\Properties\PropertyNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class PreAuctionAcquisitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = ['state' => strtoupper(trim((string) ($this->input('state') ?: 'FL')))];
        foreach (['owner_contact_id', 'property_id', 'assigned_user_id', 'auction_at', 'purchase_deadline', 'contract_date', 'closing_date', 'deed_recorded_date', 'non_redemption_reviewed_at', 'entitlement_reviewed_at', 'claim_submitted_at', 'paid_at'] as $field) {
            $data[$field] = filled($this->input($field)) ? $this->input($field) : null;
        }
        foreach (['parcel_id', 'tax_deed_number', 'certificate_number', 'appraiser_url', 'property_details_url', 'auction_url', 'recording_instrument_number', 'document_drive_url'] as $field) {
            if ($this->exists($field)) $data[$field] = filled($this->input($field)) ? trim((string) $this->input($field)) : null;
        }
        $this->merge($data);
    }

    public function rules(): array
    {
        $financialRule = Rule::prohibitedIf(! $this->user()?->canViewPreAuctionFinancials());
        $documentRule = Rule::prohibitedIf(! $this->user()?->canViewPropertySourceDocuments());
        $money = [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'];

        return [
            'status' => ['required', Rule::enum(PreAuctionAcquisitionStatus::class)],
            'owner_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'source' => ['nullable', 'string', 'max:120'],
            'state' => ['required', Rule::in(['FL'])],
            'county' => ['required', 'string', 'max:120'],
            'parcel_id' => ['required', 'string', 'max:120'],
            'tax_deed_number' => ['nullable', 'string', 'max:120'],
            'certificate_number' => ['nullable', 'string', 'max:120'],
            'assessor_market_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'appraiser_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'property_details_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'auction_at' => ['required', 'date'],
            'auction_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'purchase_deadline' => ['nullable', 'date', 'before:auction_at'],
            'contract_date' => ['nullable', 'date'],
            'closing_date' => ['nullable', 'date', 'after_or_equal:contract_date', 'before_or_equal:auction_at'],
            'deed_recorded_date' => ['nullable', 'date', 'after_or_equal:closing_date', 'before_or_equal:auction_at'],
            'recording_instrument_number' => ['nullable', 'string', 'max:160'],
            'non_redemption_reviewed_at' => ['nullable', 'date', 'before_or_equal:auction_at'],
            'purchase_price' => $money, 'closing_costs' => $money, 'other_costs' => $money,
            'projected_surplus' => $money, 'auction_winning_bid' => $money, 'surplus_generated' => $money,
            'entitlement_status' => ['required', Rule::enum(PreAuctionEntitlementStatus::class)],
            'entitlement_reviewed_at' => ['nullable', 'date', 'after_or_equal:deed_recorded_date'],
            'entitlement_notes' => ['nullable', 'string', 'max:10000'],
            'claim_submitted_at' => ['nullable', 'date', 'after_or_equal:auction_at'],
            'paid_at' => ['nullable', 'date', 'after_or_equal:claim_submitted_at'],
            'amount_recovered' => $money,
            'document_drive_url' => [$documentRule, 'nullable', 'string', 'max:2048', 'url:https'],
            'notes' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('status') === PreAuctionAcquisitionStatus::Paid->value && ! $this->filled('amount_recovered')) {
                $validator->errors()->add('amount_recovered', 'Enter the amount recovered before marking the acquisition Paid.');
            }

            $entitlementReviewed = $this->input('entitlement_status') !== PreAuctionEntitlementStatus::NotReviewed->value;
            if ($entitlementReviewed && ! $this->filled('entitlement_notes')) {
                $validator->errors()->add('entitlement_notes', 'Document the basis for the entitlement review.');
            }
            if ($entitlementReviewed && ! $this->filled('deed_recorded_date')) {
                $validator->errors()->add('deed_recorded_date', 'Record the deed before completing an entitlement review.');
            }
            if ($this->filled('claim_submitted_at') && $this->input('entitlement_status') !== PreAuctionEntitlementStatus::Eligible->value) {
                $validator->errors()->add('claim_submitted_at', 'A claim may only be recorded after the entitlement review is marked Eligible.');
            }

            if ($this->filled('parcel_id') && $this->filled('auction_at') && ! $validator->errors()->hasAny(['parcel_id', 'auction_at'])) {
                $normalizedParcel = app(PropertyNormalizer::class)->parcelId($this->input('parcel_id'));
                $parcelQuery = PreAuctionAcquisition::query()->where('state', 'FL')->where('county', $this->input('county'))
                    ->where('normalized_parcel_id', $normalizedParcel)
                    ->whereDate('auction_at', date('Y-m-d', strtotime((string) $this->input('auction_at'))));
                if ($case = $this->route('preAuction')) $parcelQuery->whereKeyNot($case->id);
                if ($parcelQuery->exists()) $validator->errors()->add('parcel_id', 'This parcel already has a pre-auction acquisition for the same scheduled auction date.');
            }

            if (! $this->filled('tax_deed_number')) return;
            $query = PreAuctionAcquisition::query()
                ->where('state', 'FL')->where('county', $this->input('county'))
                ->where('tax_deed_number', $this->input('tax_deed_number'));
            if ($case = $this->route('preAuction')) $query->whereKeyNot($case->id);
            if ($query->exists()) $validator->errors()->add('tax_deed_number', 'This Florida county and tax deed number already has a pre-auction acquisition file.');
        }];
    }
}
