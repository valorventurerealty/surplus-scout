<?php

namespace App\Http\Requests;

use App\Enums\SopDepartment;
use App\Enums\SopStatus;
use App\Models\Sop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class SopRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'owner_user_id' => filled($this->input('owner_user_id')) ? $this->input('owner_user_id') : null,
            'next_sop_id' => filled($this->input('next_sop_id')) ? $this->input('next_sop_id') : null,
            'effective_date' => filled($this->input('effective_date')) ? $this->input('effective_date') : null,
            'review_date' => filled($this->input('review_date')) ? $this->input('review_date') : null,
            'drive_url' => filled($this->input('drive_url')) ? trim((string) $this->input('drive_url')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'department' => ['required', Rule::enum(SopDepartment::class)],
            'status' => ['required', Rule::enum(SopStatus::class)],
            'next_sop_id' => ['nullable', 'integer', Rule::exists('sops', 'id')->whereNull('deleted_at')],
            'version_label' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'owner_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'summary' => ['nullable', 'string', 'max:5000'],
            'content_text' => ['nullable', 'string', 'max:500000'],
            'effective_date' => ['nullable', 'date'],
            'review_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'drive_url' => ['nullable', 'url:https', 'max:2048'],
            'sop_file' => ['nullable', 'file', 'max:15360', 'mimes:pdf,doc,docx,txt,md,rtf'],
            'remove_file' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $sop = $this->route('sop');
            $willHaveFile = $this->hasFile('sop_file') || ($sop instanceof Sop && $sop->hasFile() && ! $this->boolean('remove_file'));
            if (! $willHaveFile && blank($this->input('content_text')) && blank($this->input('drive_url'))) {
                $validator->errors()->add('sop_file', 'Upload a file, enter the procedure, or provide an HTTPS Drive link.');
            }
            if ($sop instanceof Sop && (int) $this->input('next_sop_id') === $sop->id) {
                $validator->errors()->add('next_sop_id', 'An SOP cannot point to itself as the next SOP.');
            }
            if ($sop instanceof Sop && filled($this->input('next_sop_id')) && ! $validator->errors()->has('next_sop_id')) {
                $links = Sop::query()->pluck('next_sop_id', 'id');
                $cursor = (int) $this->input('next_sop_id');
                $visited = [];

                while ($cursor) {
                    if ($cursor === $sop->id || isset($visited[$cursor])) {
                        $validator->errors()->add('next_sop_id', 'This selection would create a circular SOP sequence.');
                        break;
                    }

                    $visited[$cursor] = true;
                    $cursor = (int) ($links[$cursor] ?? 0);
                }
            }
        }];
    }
}
