<?php

namespace App\Http\Controllers;

use App\Models\SurplusCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SurplusScoutController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('viewAny', SurplusCase::class);

        return view('surplus-scout.index');
    }
}
