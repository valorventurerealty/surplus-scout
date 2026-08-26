<?php

namespace App\Http\Requests;

use App\Models\ArmoryScript;

class StoreArmoryScriptRequest extends ArmoryScriptRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ArmoryScript::class) ?? false;
    }
}
