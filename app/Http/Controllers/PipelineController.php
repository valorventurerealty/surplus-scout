<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Http\Requests\UpdatePipelineStageRequest;
use App\Models\Property;
use App\Services\PropertyPipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PipelineController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Property::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2', 'regex:/^[a-zA-Z]{2}$/'],
            'property_type' => ['nullable', Rule::enum(PropertyType::class)],
        ]);

        $query = Property::query()
            ->with('ownerContact:id,first_name,last_name,company')
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('parcel_id', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('county', 'like', "%{$search}%");
            }))
            ->when($validated['state'] ?? null, fn ($query, $state) => $query->where('state', strtoupper($state)))
            ->when($validated['property_type'] ?? null, fn ($query, $type) => $query->where('property_type', $type));

        $properties = $query->orderByDesc('updated_at')->get();
        $columns = collect(PropertyStatus::cases())->mapWithKeys(fn (PropertyStatus $status): array => [
            $status->value => [
                'status' => $status,
                'properties' => $properties->where('status', $status),
            ],
        ]);

        return view('pipeline.index', [
            'columns' => $columns,
            'totalProperties' => $properties->count(),
            'soldProperties' => $properties->where('status', PropertyStatus::Sold)->count(),
            'activeProperties' => $properties->reject(fn (Property $property): bool => in_array(
                $property->status,
                [PropertyStatus::Sold, PropertyStatus::Archived],
                true,
            ))->count(),
            'pipelineValue' => $request->user()->canViewPropertyFinancials()
                ? $properties
                    ->filter(fn (Property $property): bool => $property->status->countsTowardPortfolioValue())
                    ->sum(fn (Property $property): float => (float) ($property->expected_sales_price ?? 0))
                : null,
        ]);
    }

    public function update(
        UpdatePipelineStageRequest $request,
        Property $property,
        PropertyPipelineService $service,
    ): RedirectResponse {
        $status = PropertyStatus::from($request->validated('status'));
        $previous = $property->status;
        $service->move($property, $status, $request->user());

        return back()->with('success', $previous === $status
            ? 'Property is already in that pipeline stage.'
            : "Property moved to {$status->label()}.");
    }
}
