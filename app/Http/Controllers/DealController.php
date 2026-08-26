<?php

namespace App\Http\Controllers;

use App\Enums\DealContactRole;
use App\Enums\DealStatus;
use App\Enums\DealType;
use App\Enums\ContactType;
use App\Http\Requests\StoreDealRequest;
use App\Http\Requests\UpdateDealRequest;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Models\User;
use App\Services\DealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DealController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Deal::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(DealType::class)],
            'status' => ['nullable', Rule::enum(DealStatus::class)],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'sort' => ['nullable', Rule::in(array_values(array_filter([
                'deal', 'property', 'primary_contact', 'assigned', 'close_date', 'status',
                $request->user()->canViewPropertyFinancials() ? 'contract_projected' : null,
            ])))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $base = Deal::query()->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', DealType::PreTaxAuctionAcquisition->value));
        $metrics = ['open' => (clone $base)->open()->count(), 'under_contract' => (clone $base)->where('status', DealStatus::UnderContract)->count(), 'closing' => (clone $base)->where('status', DealStatus::Closing)->count(), 'closed' => (clone $base)->where('status', DealStatus::Closed)->count()];
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $deals = Deal::query()->with(['property:id,address,city,state', 'primaryContact:id,first_name,last_name,company', 'assignedUser:id,name'])
            ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', DealType::PreTaxAuctionAcquisition->value))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('deal_number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['assigned_user_id'] ?? null, fn ($query, $userId) => $query->where('assigned_user_id', $userId));
        match ($sort) {
            'deal' => $deals->orderBy('title', $direction)->orderBy('deal_number', $direction),
            'property' => $deals->orderBy(Property::query()->select('address')->whereColumn('properties.id', 'deals.property_id')->limit(1), $direction),
            'primary_contact' => $deals
                ->orderBy(Contact::query()->select('last_name')->whereColumn('contacts.id', 'deals.primary_contact_id')->limit(1), $direction)
                ->orderBy(Contact::query()->select('first_name')->whereColumn('contacts.id', 'deals.primary_contact_id')->limit(1), $direction),
            'assigned' => $deals->orderBy(User::query()->select('name')->whereColumn('users.id', 'deals.assigned_user_id')->limit(1), $direction),
            'close_date' => $deals->orderBy('projected_close_date', $direction),
            'contract_projected' => $deals->orderBy('contract_amount', $direction)->orderBy('projected_revenue', $direction),
            'status' => $deals->orderBy('status', $direction),
            default => $deals->orderBy('created_at', $direction),
        };
        $deals = $deals->orderBy('id', $direction)->paginate(25)->withQueryString();
        return view('deals.index', ['deals' => $deals, 'metrics' => $metrics, ...$this->formData()]);
    }

    public function create(): View { Gate::authorize('create', Deal::class); return view('deals.create', $this->formData()); }
    public function store(StoreDealRequest $request, DealService $service): RedirectResponse { $deal = $service->create($request->validated(), $request->user()); return redirect()->route('deals.show', $deal)->with('success', 'Deal created.'); }

    public function show(Deal $deal): View
    {
        Gate::authorize('view', $deal);
        $deal->load(['property:id,address,city,state,postal_code,status', 'primaryContact:id,first_name,last_name,company,email,phone', 'assignedUser:id,name,email', 'contacts:id,first_name,last_name,company,email,phone', 'tasks.assignedUser:id,name']);
        if (request()->user()->canViewPropertyFinancials() && $deal->property_id) {
            $deal->load(['property' => fn ($query) => $query->select([
                'id', 'address', 'city', 'state', 'postal_code', 'status', 'purchase_price', 'taxes',
                'attorney_fees', 'realtor_fees', 'other_costs', 'all_in_amount',
                'expected_sales_price', 'expected_profit', 'actual_sales_price', 'actual_profit',
            ])]);
        }
        return view('deals.show', [
            'deal' => $deal,
            'contacts' => Contact::query()
                ->when(! request()->user()->canViewSurplusCases(), fn ($query) => $query->where('type', '!=', ContactType::Surplus->value))
                ->when(! request()->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', ContactType::PreTaxAuctions->value))
                ->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
            'contactRoles' => DealContactRole::cases(),
        ]);
    }

    public function edit(Deal $deal): View { Gate::authorize('update', $deal); return view('deals.edit', ['deal' => $deal, ...$this->formData()]); }
    public function update(UpdateDealRequest $request, Deal $deal, DealService $service): RedirectResponse { $service->update($deal, $request->validated(), $request->user()); return redirect()->route('deals.show', $deal)->with('success', 'Deal updated.'); }
    public function destroy(Request $request, Deal $deal): RedirectResponse { Gate::authorize('delete', $deal); $deal->updateQuietly(['updated_by' => $request->user()->id]); $deal->delete(); return redirect()->route('deals.index')->with('success', 'Deal archived.'); }

    private function formData(): array
    {
        $user = request()->user();

        return [
            'dealTypes' => array_values(array_filter(DealType::cases(), fn (DealType $type): bool => $type !== DealType::PreTaxAuctionAcquisition || $user->canViewPreAuctionAcquisitions())),
            'dealStatuses' => DealStatus::cases(),
            'properties' => Property::query()->orderBy('address')->get(['id', 'address', 'city', 'state']),
            'contacts' => Contact::query()
                ->when(! $user->canViewSurplusCases(), fn ($query) => $query->where('type', '!=', ContactType::Surplus->value))
                ->when(! $user->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', ContactType::PreTaxAuctions->value))
                ->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
