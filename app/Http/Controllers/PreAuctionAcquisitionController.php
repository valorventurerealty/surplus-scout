<?php

namespace App\Http\Controllers;

use App\Enums\PreAuctionAcquisitionStatus;
use App\Enums\PreAuctionContactRole;
use App\Enums\PreAuctionEntitlementStatus;
use App\Http\Requests\BulkUpdatePreAuctionStageRequest;
use App\Http\Requests\StorePreAuctionAcquisitionRequest;
use App\Http\Requests\UpdatePreAuctionAcquisitionRequest;
use App\Models\Contact;
use App\Models\PreAuctionAcquisition;
use App\Models\Property;
use App\Models\User;
use App\Services\PreAuctionAcquisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PreAuctionAcquisitionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PreAuctionAcquisition::class);
        $financial = $request->user()->canViewPreAuctionFinancials();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PreAuctionAcquisitionStatus::class)],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'auction' => ['nullable', Rule::in(['past', 'next_30_days', 'next_90_days'])],
            'sort' => ['nullable', Rule::in(array_values(array_filter(['case', 'owner', 'property', 'assigned', 'auction', 'status', $financial ? 'economics' : null])))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $metrics = [
            'open' => PreAuctionAcquisition::query()->open()->count(),
            'next_30_days' => PreAuctionAcquisition::query()->open()->whereBetween('auction_at', [now(), now()->addDays(30)])->count(),
            'deed_recorded' => PreAuctionAcquisition::query()->whereNotNull('deed_recorded_date')->count(),
            'surplus_review' => PreAuctionAcquisition::query()->where('status', PreAuctionAcquisitionStatus::SurplusReview)->count(),
            'projected_net' => $financial ? PreAuctionAcquisition::query()->open()->sum('projected_net') : null,
        ];
        $sort = $validated['sort'] ?? 'auction';
        $direction = $validated['direction'] ?? 'asc';
        $cases = PreAuctionAcquisition::query()
            ->with(['ownerContact:id,first_name,last_name,company', 'property:id,address,city,state', 'assignedUser:id,name'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('case_number', 'like', "%{$search}%")->orWhere('tax_deed_number', 'like', "%{$search}%")
                ->orWhere('parcel_id', 'like', "%{$search}%")->orWhere('county', 'like', "%{$search}%")
                ->orWhereHas('ownerContact', fn ($contact) => $contact->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                ->orWhereHas('property', fn ($property) => $property->where('address', 'like', "%{$search}%"))))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['assigned_user_id'] ?? null, fn ($query, $id) => $query->where('assigned_user_id', $id))
            ->when(($validated['auction'] ?? null) === 'past', fn ($query) => $query->where('auction_at', '<', now()))
            ->when(($validated['auction'] ?? null) === 'next_30_days', fn ($query) => $query->whereBetween('auction_at', [now(), now()->addDays(30)]))
            ->when(($validated['auction'] ?? null) === 'next_90_days', fn ($query) => $query->whereBetween('auction_at', [now(), now()->addDays(90)]));

        match ($sort) {
            'case' => $cases->orderBy('case_number', $direction),
            'owner' => $cases->orderBy(Contact::query()->select('last_name')->whereColumn('contacts.id', 'pre_auction_acquisitions.owner_contact_id')->limit(1), $direction),
            'property' => $cases->orderBy(Property::query()->select('address')->whereColumn('properties.id', 'pre_auction_acquisitions.property_id')->limit(1), $direction),
            'assigned' => $cases->orderBy(User::query()->select('name')->whereColumn('users.id', 'pre_auction_acquisitions.assigned_user_id')->limit(1), $direction),
            'auction' => $cases->orderBy('auction_at', $direction),
            'economics' => $cases->orderBy('projected_surplus', $direction)->orderBy('projected_net', $direction),
            'status' => $cases->orderByPipelineStatus($direction),
            default => $cases->orderBy('auction_at', 'asc'),
        };

        return view('pre-auction.index', [
            'cases' => $cases->orderBy('id', $direction)->paginate(25)->withQueryString(),
            'metrics' => $metrics, ...$this->formData(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PreAuctionAcquisition::class);
        return view('pre-auction.create', $this->formData());
    }

    public function store(StorePreAuctionAcquisitionRequest $request, PreAuctionAcquisitionService $service): RedirectResponse
    {
        $case = $service->create($request->validated(), $request->user());
        return redirect()->route('pre-auction.show', $case)->with('success', 'Pre-auction acquisition created.');
    }

    public function show(PreAuctionAcquisition $preAuction): View
    {
        Gate::authorize('view', $preAuction);
        $preAuction->load(['ownerContact', 'contacts', 'property:id,address,city,state,postal_code,parcel_id', 'assignedUser:id,name,email', 'nonRedemptionReviewer:id,name', 'entitlementReviewer:id,name', 'tasks.assignedUser:id,name']);

        return view('pre-auction.show', [
            'case' => $preAuction,
            'availableContacts' => Contact::query()->whereNotIn('id', $preAuction->contacts->pluck('id'))->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
            'contactRoles' => PreAuctionContactRole::cases(),
        ]);
    }

    public function edit(PreAuctionAcquisition $preAuction): View
    {
        Gate::authorize('update', $preAuction);
        return view('pre-auction.edit', ['case' => $preAuction, ...$this->formData()]);
    }

    public function update(UpdatePreAuctionAcquisitionRequest $request, PreAuctionAcquisition $preAuction, PreAuctionAcquisitionService $service): RedirectResponse
    {
        $service->update($preAuction, $request->validated(), $request->user());
        return redirect()->route('pre-auction.show', $preAuction)->with('success', 'Pre-auction acquisition updated.');
    }

    public function bulkUpdateStage(BulkUpdatePreAuctionStageRequest $request, PreAuctionAcquisitionService $service): RedirectResponse
    {
        $data = $request->validated();
        $cases = PreAuctionAcquisition::query()->whereKey($data['case_ids'])->get();

        foreach ($cases as $case) {
            Gate::authorize('update', $case);
        }

        $status = PreAuctionAcquisitionStatus::from($data['status']);
        $updated = $service->bulkUpdateStage($cases->modelKeys(), $status, $request->user());

        return back()->with(
            'success',
            $updated.' PreTax Auction '.str('file')->plural($updated).' moved to '.$status->label().'.',
        );
    }

    public function destroy(Request $request, PreAuctionAcquisition $preAuction): RedirectResponse
    {
        Gate::authorize('delete', $preAuction);
        $preAuction->updateQuietly(['updated_by' => $request->user()->id]);
        $preAuction->delete();
        return redirect()->route('pre-auction.index')->with('success', 'Pre-auction acquisition archived.');
    }

    private function formData(): array
    {
        return [
            'statuses' => PreAuctionAcquisitionStatus::cases(),
            'entitlementStatuses' => PreAuctionEntitlementStatus::cases(),
            'contacts' => Contact::query()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
            'properties' => Property::query()->orderBy('address')->get(['id', 'address', 'city', 'state', 'parcel_id']),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
