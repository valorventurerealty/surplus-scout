<?php

namespace App\Http\Requests;

use App\Models\ArmoryEmailTemplate;

class UpdateArmoryEmailTemplateRequest extends ArmoryEmailTemplateRequest
{
    public function authorize(): bool
    {
        $template = $this->route('emailTemplate');

        return $template instanceof ArmoryEmailTemplate
            && ($this->user()?->can('update', $template) ?? false);
    }
}
