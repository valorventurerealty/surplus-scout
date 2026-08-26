<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtractPropertyIntakeRequest;
use App\Services\PropertyIntakeService;
use Illuminate\Http\RedirectResponse;

class PropertyIntakeController extends Controller
{
    public function extract(ExtractPropertyIntakeRequest $request, PropertyIntakeService $service): RedirectResponse
    {
        $intake = $service->extract($request->file('document'), $request->user());
        $review = $service->review($intake, $request->user());

        return redirect()->route('properties.create')
            ->withInput([...$review['values'], 'intake_token' => $intake->token])
            ->with('property_extraction', $review['summary'])
            ->with('success', 'Candidate property information extracted. Review every field before creating the property.');
    }
}
