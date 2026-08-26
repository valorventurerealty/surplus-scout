<?php

namespace App\Http\Controllers;

use App\Models\ArmoryScript;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ArmorySalesCopilotController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('viewAny', ArmoryScript::class);

        return view('armory.sales-copilot.index');
    }
}
