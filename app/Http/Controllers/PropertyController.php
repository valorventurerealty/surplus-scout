<?php

namespace App\Http\Controllers;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\WetlandsStatus;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Models\Contact;
use App\Models\Property;
use App\Services\PropertyService;
use App\Models\PropertyIntakeFile;
use App\Services\PropertyChecklistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Property::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PropertyStatus::class)],
            'property_type' => ['nullable', Rule::enum(PropertyType::class)],
            'state' => ['nullable', 'string', 'size:2', 'regex:/^[a-zA-Z]{2}$/'],
            'sort' => ['nullable', Rule::in(array_values(array_filter([
                'property', 'parcel_county', 'owner', 'type', 'acreage', 'status',
                $request->user()->canViewPropertyFinancials() ? 'all_in_investor' : null,
                $request->user()->canViewPropertyFinancials() ? 'expected_sale_profit' : null,
            ])))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';

        $properties = Property::query()
            ->with('ownerContact:id,first_name,last_name,company')
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('parcel_id', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('county', 'like', "%{$search}%");
            }))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['property_type'] ?? null, fn ($query, $type) => $query->where('property_type', $type))
            ->when($validated['state'] ?? null, fn ($query, $state) => $query->where('state', strtoupper($state)));

        match ($sort) {
            'property' => $properties->orderBy('address', $direction)->orderBy('city', $direction),
            'parcel_county' => $properties->orderBy('parcel_id', $direction)->orderBy('county', $direction),
            'owner' => $properties
                ->orderBy(Contact::query()->select('last_name')->whereColumn('contacts.id', 'properties.owner_contact_id')->limit(1), $direction)
                ->orderBy(Contact::query()->select('first_name')->whereColumn('contacts.id', 'properties.owner_contact_id')->limit(1), $direction),
            'type' => $properties->orderBy('property_type', $direction),
            'acreage' => $properties->orderBy('acreage', $direction),
            'all_in_investor' => $properties->orderBy('all_in_amount', $direction)->orderBy('investor_price', $direction),
            'expected_sale_profit' => $properties->orderBy('expected_sales_price', $direction)->orderBy('expected_profit', $direction),
            'status' => $properties->orderByPipelineStatus($direction),
            default => $properties->orderBy('created_at', $direction),
        };

        $properties = $properties
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString();

        return view('properties.index', compact('properties'));
    }

    public function create(): View
    {
        Gate::authorize('create', Property::class);

        return view('properties.create', $this->formData());
    }

    public function store(StorePropertyRequest $request, PropertyService $service): RedirectResponse
    {
        $conversation = null;
        if ($request->filled('intake_token')) {
            $conversation = PropertyIntakeFile::query()
                ->where('token', $request->string('intake_token')->toString())
                ->where('user_id', $request->user()->id)
                ->with('aiConversation')
                ->first()?->aiConversation;
        }
        $property = $service->create($request->validated(), $request->user());

        if ($conversation) {
            return redirect()->route('vvr-ai.conversations.show', $conversation)
                ->with('success', 'VVR AI completed the approved property creation successfully.');
        }

        return redirect()->route('properties.show', $property)->with('success', 'Property created.');
    }

    public function show(Property $property, PropertyChecklistService $checklistService): View
    {
        Gate::authorize('view', $property);
        $property->load([
            'ownerContact:id,first_name,last_name,company,email,phone',
            'contacts:id,first_name,last_name,company,type,status',
            'deals:id,token,deal_number,title,type,status,property_id,projected_close_date',
        ]);
        if (request()->user()->canViewSurplusCases()) {
            $property->load(['surplusCases:id,token,case_number,status,claimant_contact_id,property_id,claim_deadline']);
        }
        if (request()->user()->canViewPreAuctionAcquisitions()) {
            $property->load(['preAuctionAcquisitions:id,token,case_number,status,owner_contact_id,property_id,auction_at']);
        }

        if (Gate::allows('viewSourceDocuments', $property)) {
            $property->load([
                'intakeFiles' => fn ($query) => $query->where('status', 'attached')->latest(),
                'surplusIntakeFiles' => fn ($query) => $query->where('status', 'attached')->latest(),
            ]);
        }

        if (Gate::allows('viewFinancials', $property)) {
            $property->load(['financialSplit.contactOne', 'financialSplit.contactTwo', 'taxRecords' => fn ($query) => $query->latest('tax_year')]);
        }

        return view('properties.show', [
            'property' => $property,
            'checklistItems' => $checklistService->forProperty($property),
        ]);
    }

    public function edit(Property $property): View
    {
        Gate::authorize('update', $property);

        return view('properties.edit', ['property' => $property, ...$this->formData()]);
    }

    public function update(UpdatePropertyRequest $request, Property $property, PropertyService $service): RedirectResponse
    {
        $service->update($property, $request->validated(), $request->user());

        return redirect()->route('properties.show', $property)->with('success', 'Property updated.');
    }

    public function destroy(Request $request, Property $property): RedirectResponse
    {
        Gate::authorize('delete', $property);
        $property->updateQuietly(['updated_by' => $request->user()->id]);
        $property->delete();

        return redirect()->route('properties.index')->with('success', 'Property archived.');
    }

    private function formData(): array
    {
        return [
            'propertyTypes' => PropertyType::cases(),
            'propertyStatuses' => PropertyStatus::cases(),
            'wetlandsStatuses' => WetlandsStatus::cases(),
            'contacts' => Contact::query()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
        ];
    }
}
