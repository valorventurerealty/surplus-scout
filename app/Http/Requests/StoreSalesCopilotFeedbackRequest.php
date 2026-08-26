<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesCopilotFeedbackRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->is_active; }
    public function rules(): array { return ['rating'=>['required',Rule::in(['worked','edited','missed'])],'final_wording'=>['nullable','string','max:4000'],'notes'=>['nullable','string','max:4000'],'save_to_playbook'=>['nullable','boolean']]; }
}
