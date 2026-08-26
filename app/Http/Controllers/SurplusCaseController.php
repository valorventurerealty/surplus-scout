<?php

namespace App\Http\Controllers;

use App\Enums\SurplusCaseStatus;
use App\Enums\SurplusContactRole;
use App\Http\Requests\BulkUpdateSurplusStageRequest;
use App\Http\Requests\StoreSurplusCaseRequest;
use App\Http\Requests\UpdateSurplusCaseRequest;
use App\Models\Contact;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\User;
use App\Services\SurplusCaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SurplusCaseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SurplusCase::class);
        $financial = $request->user()->canViewSurplusFinancials();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(SurplusCaseStatus::class)],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'deadline' => ['nullable', Rule::in(['overdue', 'next_30_days', 'no_deadline'])],
            'sort' => ['nullable', Rule::in(array_values(array_filter(['case', 'claimant', 'property', 'assigned', 'deadline', 'status', $financial ? 'surplus_fee' : null])))],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $metrics = [
            'open' => SurplusCase::query()->open()->count(),
            'submit_claim' => SurplusCase::query()->where('status', SurplusCaseStatus::SubmitClaim)->count(),
            'approved' => SurplusCase::query()->where('status', SurplusCaseStatus::Approved)->count(),
            'paid' => SurplusCase::query()->where('status', SurplusCaseStatus::Paid)->count(),
            'expected_fees' => $financial ? SurplusCase::query()->open()->sum('expected_fee') : null,
        ];
        $sort = $validated['sort'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $cases = SurplusCase::query()->with(['claimantContact:id,first_name,last_name,company', 'property:id,address,city,state', 'assignedUser:id,name'])
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('case_number', 'like', "%{$search}%")
                ->orWhere('tax_deed_number', 'like', "%{$search}%")
                ->orWhere('foreclosure_case_number', 'like', "%{$search}%")
                ->orWhere('parcel_id', 'like', "%{$search}%")
                ->orWhere('county', 'like', "%{$search}%")
                ->orWhereHas('claimantContact', fn ($contact) => $contact->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                ->orWhereHas('contacts', fn ($contact) => $contact->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                ->orWhereHas('property', fn ($property) => $property->where('address', 'like', "%{$search}%"))))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['assigned_user_id'] ?? null, fn ($query, $id) => $query->where('assigned_user_id', $id))
            ->when(($validated['deadline'] ?? null) === 'overdue', fn ($query) => $query->whereDate('claim_deadline', '<', today())->open())
            ->when(($validated['deadline'] ?? null) === 'next_30_days', fn ($query) => $query->whereBetween('claim_deadline', [today(), today()->addDays(30)]))
            ->when(($validated['deadline'] ?? null) === 'no_deadline', fn ($query) => $query->whereNull('claim_deadline'));

        match ($sort) {
            'case' => $cases->orderBy('case_number', $direction),
            'claimant' => $cases->orderBy(Contact::query()->select('last_name')->whereColumn('contacts.id', 'surplus_cases.claimant_contact_id')->limit(1), $direction),
            'property' => $cases->orderBy(Property::query()->select('address')->whereColumn('properties.id', 'surplus_cases.property_id')->limit(1), $direction),
            'assigned' => $cases->orderBy(User::query()->select('name')->whereColumn('users.id', 'surplus_cases.assigned_user_id')->limit(1), $direction),
            'deadline' => $cases->orderBy('claim_deadline', $direction),
            'surplus_fee' => $cases->orderBy('surplus_amount', $direction)->orderBy('expected_fee', $direction),
            'status' => $cases->orderByPipelineStatus($direction),
            default => $cases->orderBy('created_at', $direction),
        };

        return view('surplus.index', [
            'cases' => $cases->orderBy('id', $direction)->paginate(25)->withQueryString(),
            'metrics' => $metrics,
            ...$this->formData(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SurplusCase::class);
        return view('surplus.create', $this->formData());
    }

    public function store(StoreSurplusCaseRequest $request, SurplusCaseService $service): RedirectResponse
    {
        $case = $service->create($request->validated(), $request->user());
        return redirect()->route('surplus.show', $case)->with('success', 'Surplus case created.');
    }

    public function show(SurplusCase $surplus): View
    {
        Gate::authorize('view', $surplus);
        $surplus->load(['claimantContact:id,first_name,last_name,company,email,phone,mailing_address_line_1,mailing_address_line_2,mailing_city,mailing_state_province,mailing_postal_code,mailing_country', 'contacts:id,first_name,last_name,company,email,phone', 'property:id,address,city,state,postal_code,parcel_id', 'assignedUser:id,name,email', 'tasks.assignedUser:id,name']);
        if (Gate::allows('viewDocuments', $surplus)) {
            $surplus->load(['intakeFiles' => fn ($query) => $query->where('status', 'attached')->latest()]);
        }
        return view('surplus.show', [
            'case' => $surplus,
            'availableContacts' => Contact::query()->whereNotIn('id', $surplus->contacts->pluck('id'))->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
            'contactRoles' => SurplusContactRole::cases(),
        ]);
    }

    public function edit(SurplusCase $surplus): View
    {
        Gate::authorize('update', $surplus);
        return view('surplus.edit', ['case' => $surplus, ...$this->formData()]);
    }

    public function update(UpdateSurplusCaseRequest $request, SurplusCase $surplus, SurplusCaseService $service): RedirectResponse
    {
        $service->update($surplus, $request->validated(), $request->user());
        return redirect()->route('surplus.show', $surplus)->with('success', 'Surplus case updated.');
    }

    public function bulkUpdateStage(BulkUpdateSurplusStageRequest $request, SurplusCaseService $service): RedirectResponse
    {
        $data = $request->validated();
        $cases = SurplusCase::query()->whereKey($data['case_ids'])->get();

        foreach ($cases as $case) {
            Gate::authorize('update', $case);
        }

        if ($data['operation'] === 'county') {
            $updated = $service->bulkUpdateCounty($cases->modelKeys(), $data['county'], $request->user());

            return back()->with('success', $updated.' Surplus '.str('case')->plural($updated).' assigned to '.$data['county'].' County.');
        }

        $status = SurplusCaseStatus::from($data['status']);
        $updated = $service->bulkUpdateStage($cases->modelKeys(), $status, $request->user());

        return back()->with('success', $updated.' Surplus '.str('case')->plural($updated).' moved to '.$status->label().'.');
    }

    public function destroy(Request $request, SurplusCase $surplus): RedirectResponse
    {
        Gate::authorize('delete', $surplus);
        $surplus->updateQuietly(['updated_by' => $request->user()->id]);
        $surplus->delete();
        return redirect()->route('surplus.index')->with('success', 'Surplus case archived.');
    }

    private function formData(): array
    {
        return [
            'statuses' => SurplusCaseStatus::cases(),
            'counties' => SurplusCase::query()->whereNotNull('county')->where('county', '!=', '')->distinct()->orderBy('county')->pluck('county'),
            'contacts' => Contact::query()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
            'properties' => Property::query()->orderBy('address')->get(['id', 'address', 'city', 'state', 'parcel_id']),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
