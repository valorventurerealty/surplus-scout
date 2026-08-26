<?php

namespace App\Http\Controllers;

use App\Enums\NegotiationPlanStatus;
use App\Http\Requests\StoreNegotiationPlanRequest;
use App\Http\Requests\UpdateNegotiationPlanRequest;
use App\Models\Contact;
use App\Models\NegotiationPlan;
use App\Models\Property;
use App\Services\NegotiationLadderCalculator;
use App\Services\NegotiationPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArmoryNegotiationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', NegotiationPlan::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(NegotiationPlanStatus::class)],
        ]);

        $negotiations = NegotiationPlan::query()
            ->with(['property:id,address,city,state', 'buyerContact:id,first_name,last_name,company'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('property', fn ($query) => $query->where('address', 'like', "%{$search}%"))
                    ->orWhereHas('buyerContact', fn ($query) => $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%"));
            }))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('armory.negotiations.index', compact('negotiations'));
    }

    public function create(): View
    {
        Gate::authorize('create', NegotiationPlan::class);

        return view('armory.negotiations.create', $this->formData());
    }

    public function store(StoreNegotiationPlanRequest $request, NegotiationPlanService $service): RedirectResponse
    {
        $negotiation = $service->create($request->validated(), $request->user());

        return redirect()->route('armory.negotiations.show', $negotiation)->with('success', 'Negotiation plan created.');
    }

    public function show(NegotiationPlan $negotiation, NegotiationLadderCalculator $calculator): View
    {
        Gate::authorize('view', $negotiation);
        $negotiation->load(['property:id,address,city,state,postal_code', 'buyerContact:id,first_name,last_name,company']);
        $ladder = $calculator->calculate(
            $negotiation->asking_price,
            $negotiation->all_in_amount,
            $negotiation->buyer_offer,
            $negotiation->counter_percent,
        );

        return view('armory.negotiations.show', compact('negotiation', 'ladder'));
    }

    public function edit(NegotiationPlan $negotiation): View
    {
        Gate::authorize('update', $negotiation);

        return view('armory.negotiations.edit', ['negotiation' => $negotiation, ...$this->formData()]);
    }

    public function update(UpdateNegotiationPlanRequest $request, NegotiationPlan $negotiation, NegotiationPlanService $service): RedirectResponse
    {
        $service->update($negotiation, $request->validated(), $request->user());

        return redirect()->route('armory.negotiations.show', $negotiation)->with('success', 'Negotiation plan updated.');
    }

    public function destroy(Request $request, NegotiationPlan $negotiation): RedirectResponse
    {
        Gate::authorize('delete', $negotiation);
        $negotiation->updateQuietly(['updated_by' => $request->user()->id]);
        $negotiation->delete();

        return redirect()->route('armory.negotiations.index')->with('success', 'Negotiation plan archived.');
    }

    private function formData(): array
    {
        return [
            'statuses' => NegotiationPlanStatus::cases(),
            'properties' => Property::query()->orderBy('state')->orderBy('city')->orderBy('address')
                ->get(['id', 'address', 'city', 'state', 'all_in_amount', 'expected_sales_price']),
            'contacts' => Contact::query()->orderBy('last_name')->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'company']),
        ];
    }
}
