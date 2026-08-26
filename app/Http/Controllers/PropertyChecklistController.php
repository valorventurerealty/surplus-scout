<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePropertyChecklistRequest;
use App\Models\Property;
use App\Services\PropertyChecklistService;
use Illuminate\Http\RedirectResponse;

class PropertyChecklistController extends Controller
{
    public function update(
        UpdatePropertyChecklistRequest $request,
        Property $property,
        PropertyChecklistService $service,
    ): RedirectResponse {
        $service->update(
            $property,
            $request->validated('items'),
            $request->user(),
            $request->user()->canViewPropertySourceDocuments(),
        );

        return redirect(route('properties.show', $property).'#checklist')->with('success', 'Property checklist updated.');
    }
}
