<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CoachSalesCopilotRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->is_active; }
    public function rules(): array { return ['prospect_statement'=>['required','string','max:4000'],'salesperson_previous'=>['nullable','string','max:4000']]; }
}
