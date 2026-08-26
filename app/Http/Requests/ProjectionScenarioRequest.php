<?php

namespace App\Http\Requests;

use App\Enums\ProjectionScenarioStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ProjectionScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'status' => ['required', Rule::enum(ProjectionScenarioStatus::class)],
            'start_year' => ['required', 'integer', 'between:2020,2100'],
            'end_year' => ['required', 'integer', 'gte:start_year', 'between:2020,2100'],
            'contact_one_id' => [
                'nullable', 'integer', 'different:contact_two_id',
                Rule::exists('contacts', 'id')->whereNull('deleted_at'),
            ],
            'contact_two_id' => [
                'nullable', 'integer', 'different:contact_one_id',
                Rule::exists('contacts', 'id')->whereNull('deleted_at'),
            ],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->integer('end_year') - $this->integer('start_year') > 9) {
                $validator->errors()->add('end_year', 'A projection scenario may cover no more than ten years.');
            }
        }];
    }
}
