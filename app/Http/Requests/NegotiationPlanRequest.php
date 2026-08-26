<?php

namespace App\Http\Requests;

use App\Enums\NegotiationPlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class NegotiationPlanRequest extends FormRequest
{
    public function rules(): array
    {
        $money = ['required', 'numeric', 'min:0.01', 'max:999999999999.99'];

        return [
            'name' => ['required', 'string', 'max:180'],
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'sync_property_financials' => ['sometimes', 'boolean'],
            'buyer_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(NegotiationPlanStatus::class)],
            'asking_price' => $money,
            'all_in_amount' => $money,
            'buyer_offer' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'counter_percent' => ['nullable', Rule::in($this->counterPercentages())],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    private function counterPercentages(): array
    {
        $percentages = [];
        for ($basisPoints = 10000; $basisPoints >= 5000; $basisPoints -= 250) {
            $percentages[] = number_format($basisPoints / 100, 1, '.', '');
        }

        return $percentages;
    }
}
