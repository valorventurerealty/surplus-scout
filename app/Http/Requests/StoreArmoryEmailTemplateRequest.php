<?php

namespace App\Http\Requests;

use App\Models\ArmoryEmailTemplate;

class StoreArmoryEmailTemplateRequest extends ArmoryEmailTemplateRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ArmoryEmailTemplate::class) ?? false;
    }
}
