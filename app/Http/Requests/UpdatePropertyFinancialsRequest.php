<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyFinancialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canViewPropertyFinancials() ?? false;
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:999999999999.99'];

        return [
            'purchase_price' => $money,
            'taxes' => $money,
            'attorney_fees' => $money,
            'realtor_fees' => $money,
            'other_costs' => $money,
            'all_in_amount' => $money,
            'expected_sales_price' => $money,
            'actual_sales_price' => $money,
            'contact_one_id' => [
                'nullable', 'integer', 'different:contact_two_id',
                Rule::exists('contacts', 'id')->whereNull('deleted_at'),
            ],
            'contact_two_id' => [
                'nullable', 'integer', 'different:contact_one_id',
                Rule::exists('contacts', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
