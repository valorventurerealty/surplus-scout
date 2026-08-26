<?php

namespace App\Http\Requests;

use App\Enums\PropertyChecklistKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('property')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))->map(function ($item): array {
            $normalized = [
                'completed' => filter_var(data_get($item, 'completed', false), FILTER_VALIDATE_BOOL),
            ];

            if (array_key_exists('evidence_url', (array) $item)) {
                $normalized['evidence_url'] = filled(data_get($item, 'evidence_url'))
                    ? trim((string) data_get($item, 'evidence_url'))
                    : null;
            }

            return $normalized;
        })->all();

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        $keys = collect(PropertyChecklistKey::cases())->map->value->all();
        $linkRule = Rule::prohibitedIf(! $this->user()?->canViewPropertySourceDocuments());
        $rules = [
            'items' => ['required', 'array:'.implode(',', $keys)],
        ];

        foreach ($keys as $key) {
            $rules["items.{$key}"] = ['required', 'array:completed,evidence_url'];
            $rules["items.{$key}.completed"] = ['required', 'boolean'];
            $rules["items.{$key}.evidence_url"] = [$linkRule, 'nullable', 'string', 'max:2048', 'url:https'];
        }

        return $rules;
    }
}
