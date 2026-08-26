<?php

namespace App\Http\Requests;

class UpdateArmoryScriptRequest extends ArmoryScriptRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('script')) ?? false;
    }
}
