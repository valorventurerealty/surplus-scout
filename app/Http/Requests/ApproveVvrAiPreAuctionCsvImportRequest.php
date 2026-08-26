<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\PreAuctionAcquisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveVvrAiPreAuctionCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('create', Contact::class)
            && $user->can('create', PreAuctionAcquisition::class);
    }

    public function rules(): array
    {
        return [
            'selected_rows' => ['required', 'array', 'min:1', 'max:'.config('ai.pre_auction_csv_max_rows', 500)],
            'selected_rows.*' => [
                'integer', 'distinct',
                Rule::exists('ai_pre_auction_csv_import_rows', 'id')->where(
                    fn ($query) => $query->where('import_id', $this->route('import')?->id)->where('status', 'ready')
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return ['selected_rows.required' => 'Select at least one valid PreTax Auctions row to approve.'];
    }
}
