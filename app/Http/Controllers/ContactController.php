<?php

namespace App\Http\Controllers;

use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Enums\TaskPriority;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use App\Services\ContactService;
use App\Services\ContactDirectoryQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request, ContactDirectoryQuery $directory): View
    {
        Gate::authorize('viewAny', Contact::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'sort' => ['nullable', Rule::in(['name', 'company', 'email', 'associated_tasks', 'next_follow_up'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([20, 50, 100, 250])],
        ]);

        $contacts = $directory->build($request->user(), $validated)
            ->with([
                'assignedUser:id,name',
                'tasks' => fn ($query) => $query->open()
                    ->orderByRaw('due_at is null')
                    ->orderBy('due_at')
                    ->limit(3),
            ])
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return view('contacts.index', compact('contacts'));
    }

    public function create(): View
    {
        Gate::authorize('create', Contact::class);

        return view('contacts.create', $this->formData());
    }

    public function store(StoreContactRequest $request, ContactService $service): RedirectResponse
    {
        $contact = $service->create($request->validated(), $request->user());

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact created.');
    }

    public function show(Contact $contact): View
    {
        Gate::authorize('view', $contact);
        $contact->load([
            'assignedUser:id,name',
            'phoneInteractions' => fn ($query) => $query->latest('occurred_at')->limit(10),
            'properties:id,address,city,state,postal_code,parcel_id,status',
            'ownedProperties:id,owner_contact_id,address,city,state,postal_code,parcel_id,status',
            'primaryDeals' => fn ($query) => $query
                ->when(! request()->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', \App\Enums\DealType::PreTaxAuctionAcquisition->value))
                ->select(['id', 'token', 'deal_number', 'title', 'type', 'status', 'primary_contact_id', 'projected_close_date']),
            'deals' => fn ($query) => $query
                ->when(! request()->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', \App\Enums\DealType::PreTaxAuctionAcquisition->value))
                ->select(['deals.id', 'token', 'deal_number', 'title', 'type', 'status', 'projected_close_date']),
        ]);
        if (request()->user()->canViewSurplusCases()) {
            $contact->load([
                'surplusCases:id,token,case_number,status,claimant_contact_id,property_id,claim_deadline',
                'associatedSurplusCases:id,token,case_number,status,claimant_contact_id,property_id,claim_deadline',
            ]);
        }
        if (request()->user()->canViewPreAuctionAcquisitions()) {
            $contact->load([
                'preAuctionAcquisitions:id,token,case_number,status,owner_contact_id,property_id,auction_at',
                'associatedPreAuctionAcquisitions:id,token,case_number,status,owner_contact_id,property_id,auction_at',
            ]);
        }
        if (Gate::allows('viewSourceDocuments', $contact)) {
            $contact->load(['intakeFiles' => fn ($query) => $query->where('status', 'attached')->latest()]);
        }
        $tasks = $contact->tasks()
            ->with('assignedUser:id,name')
            ->orderByRaw("status = 'completed'")
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->paginate(10, ['*'], 'tasks_page');

        return view('contacts.show', [
            'contact' => $contact,
            'tasks' => $tasks,
            'taskPriorities' => TaskPriority::cases(),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Contact $contact): View
    {
        Gate::authorize('update', $contact);

        return view('contacts.edit', ['contact' => $contact, ...$this->formData($contact)]);
    }

    public function update(UpdateContactRequest $request, Contact $contact, ContactService $service): RedirectResponse
    {
        $service->update($contact, $request->validated(), $request->user());

        return redirect()->route('contacts.show', $contact)->with('success', 'Contact updated.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);
        $contact->updateQuietly(['updated_by' => $request->user()->id]);
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact archived.');
    }

    private function formData(?Contact $contact = null): array
    {
        return [
            'types' => array_values(array_filter(ContactType::cases(), fn (ContactType $type): bool => match ($type) {
                ContactType::Surplus => request()->user()->canViewSurplusCases(),
                ContactType::PreTaxAuctions => request()->user()->canViewPreAuctionAcquisitions(),
                default => true,
            })),
            'statuses' => ContactStatus::cases(),
            'assignees' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'properties' => Property::query()
                ->orderBy('state')
                ->orderBy('city')
                ->orderBy('address')
                ->get(['id', 'address', 'city', 'state', 'postal_code', 'parcel_id']),
            'selectedPropertyIds' => $contact?->properties()->pluck('properties.id')->all() ?? [],
        ];
    }
}
