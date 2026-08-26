<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueSurplusOwnerResearchRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->canManageSurplusCases() === true; }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['next_10', 'all', 'selected'])],
            'case_ids' => ['required_if:mode,selected', 'array', 'max:500'],
            'case_ids.*' => ['integer', 'distinct', 'exists:surplus_cases,id'],
        ];
    }
}
