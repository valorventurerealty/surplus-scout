<?php

namespace App\Http\Requests;

use App\Enums\ProjectionCategory;
use App\Models\ProjectionScenario;
use Illuminate\Validation\Validator;

class UpdateProjectionScenarioRequest extends ProjectionScenarioRequest
{
    public function authorize(): bool
    {
        $scenario = $this->route('scenario');

        return $scenario instanceof ProjectionScenario
            && ($this->user()?->can('update', $scenario) ?? false);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'assumptions' => ['required', 'array'],
            'assumptions.*' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'entries' => ['required', 'array'],
            'entries.*' => ['required', 'array'],
            'entries.*.*' => ['required', 'array'],
            'entries.*.*.*' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                /** @var ProjectionScenario|null $scenario */
                $scenario = $this->route('scenario');
                if ($scenario && (
                    $this->integer('start_year') !== $scenario->start_year
                    || $this->integer('end_year') !== $scenario->end_year
                )) {
                    $validator->errors()->add('start_year', 'The scenario horizon cannot be changed after creation. Create a new scenario for a different period.');
                }
                $allowedCategories = collect(ProjectionCategory::cases())->pluck('value')->all();
                $categories = array_keys((array) $this->input('assumptions', []));
                $entryCategories = array_keys((array) $this->input('entries', []));
                if (
                    array_diff($categories, $allowedCategories)
                    || array_diff($allowedCategories, $categories)
                    || array_diff($entryCategories, $allowedCategories)
                    || array_diff($allowedCategories, $entryCategories)
                ) {
                    $validator->errors()->add('entries', 'The projection contains an unsupported category.');
                }
                foreach ($allowedCategories as $category) {
                    $years = (array) data_get($this->input('entries', []), $category, []);
                    $expectedYears = $scenario?->years() ?? range($this->integer('start_year'), $this->integer('end_year'));
                    if (array_map('intval', array_keys($years)) !== $expectedYears) {
                        $validator->errors()->add("entries.{$category}", 'Every scenario year must be included.');
                    }
                    foreach ((array) $years as $year => $months) {
                        if ((int) $year < $this->integer('start_year') || (int) $year > $this->integer('end_year')) {
                            $validator->errors()->add("entries.{$category}.{$year}", 'The projection year is outside this scenario.');
                        }
                        if (array_map('intval', array_keys((array) $months)) !== range(1, 12)) {
                            $validator->errors()->add("entries.{$category}.{$year}", 'All twelve months must be included.');
                        }
                        foreach (array_keys((array) $months) as $month) {
                            if ((int) $month < 1 || (int) $month > 12) {
                                $validator->errors()->add("entries.{$category}.{$year}.{$month}", 'The projection month is invalid.');
                            }
                        }
                    }
                }
            },
        ];
    }
}
