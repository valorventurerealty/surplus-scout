<?php

namespace App\Http\Requests;

use App\Enums\PreAuctionAcquisitionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdatePreAuctionStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManagePreAuctionAcquisitions() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'case_ids' => ['required', 'array', 'min:1', 'max:200'],
            'case_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('pre_auction_acquisitions', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['required', Rule::enum(PreAuctionAcquisitionStatus::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'case_ids.required' => 'Select at least one PreTax Auction file.',
            'case_ids.min' => 'Select at least one PreTax Auction file.',
            'case_ids.max' => 'You may update up to 200 PreTax Auction files at one time.',
            'status.required' => 'Choose the stage to assign to the selected files.',
        ];
    }
}
