<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\SurplusCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveVvrAiSurplusCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->can('create', Contact::class)
            && $user->can('create', SurplusCase::class)
            && $user->canViewSurplusFinancials();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'case_state' => strtoupper(trim((string) $this->input('case_state'))),
            'county' => trim((string) $this->input('county')),
        ]);
    }

    public function rules(): array
    {
        return [
            'case_state' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'county' => ['required', 'string', 'max:120'],
            'selected_rows' => ['required', 'array', 'min:1', 'max:'.config('ai.surplus_csv_max_rows', 500)],
            'selected_rows.*' => [
                'integer', 'distinct',
                Rule::exists('ai_surplus_csv_import_rows', 'id')->where(
                    fn ($query) => $query->where('import_id', $this->route('import')?->id)->where('status', 'ready')
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return ['selected_rows.required' => 'Select at least one valid CSV row to approve.'];
    }
}
