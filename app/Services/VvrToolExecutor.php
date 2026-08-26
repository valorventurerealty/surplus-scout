<?php

namespace App\Services;

use App\Contracts\ToolRegistryInterface;
use App\Contracts\ToolExecutorInterface;
use App\Domain\Properties\PropertyNormalizer;
use App\Enums\AuctionCounty;
use App\Enums\AuctionEventType;
use App\Enums\ContactType;
use App\Enums\PropertyChecklistKey;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\SurplusCaseStatus;
use App\Enums\SopDepartment;
use App\Enums\SopStatus;
use App\Enums\WetlandsStatus;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Property;
use App\Models\Task;
use App\Models\SurplusCase;
use App\Models\Sop;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

class VvrToolExecutor implements ToolExecutorInterface
{
    public function __construct(
        private readonly ToolRegistryInterface $registry,
        private readonly PropertyPipelineService $pipelineService,
        private readonly PropertyChecklistService $checklistService,
        private readonly TaskService $taskService,
        private readonly CalendarEventService $calendarService,
        private readonly PropertyService $propertyService,
        private readonly PropertyNormalizer $propertyNormalizer,
        private readonly SurplusCaseService $surplusCaseService,
    ) {}

    public function execute(string $toolName, array $arguments, User $user): array
    {
        $definition = $this->registry->find($toolName);
        if (! $definition || ! $definition->allows($user->role)) {
            throw new AuthorizationException('This VVR AI tool is unavailable for your role.');
        }

        return match ($toolName) {
            'get_properties' => $this->getProperties($arguments, $user),
            'get_property' => $this->getProperty($arguments, $user),
            'search_buyers' => $this->searchBuyers($arguments),
            'analyze_data' => $this->analyzeData($arguments, $user),
            'change_pipeline_stage' => $this->changePipelineStage($arguments, $user),
            'update_property' => $this->updateProperty($arguments, $user),
            'update_marketability_checklist' => $this->updateChecklist($arguments, $user),
            'create_task' => $this->createTask($arguments, $user),
            'create_auction_event' => $this->createAuctionEvent($arguments, $user),
            'search_surplus_cases' => $this->searchSurplusCases($arguments, $user),
            'get_surplus_case' => $this->getSurplusCase($arguments, $user),
            'update_surplus_case' => $this->updateSurplusCase($arguments, $user),
            'search_sops' => $this->searchSops($arguments, $user),
            'get_sop' => $this->getSop($arguments, $user),
            default => throw ValidationException::withMessages(['tool' => "The {$toolName} tool is not executable in this milestone."]),
        };
    }

    private function getProperties(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PropertyStatus::class)],
            'county' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $records = Property::query()
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('address', 'like', "%{$search}%")->orWhere('parcel_id', 'like', "%{$search}%")))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['county'] ?? null, fn ($query, $county) => $query->where('county', 'like', "%{$county}%"))
            ->limit($data['limit'] ?? 20)->get();

        return ['count' => $records->count(), 'records' => $records->map(fn (Property $property) => $this->propertyResult($property, $user))->all()];
    }

    private function getProperty(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, ['property_id' => ['required', 'integer']]);
        $property = Property::query()->findOrFail($data['property_id']);
        Gate::forUser($user)->authorize('view', $property);

        return ['record' => $this->propertyResult($property, $user)];
    }

    private function propertyResult(Property $property, User $user): array
    {
        $result = [
            'id' => $property->id,
            'address' => $property->full_address,
            'parcel_id' => $property->parcel_id,
            'county' => $property->county,
            'status' => $property->status->value,
            'property_type' => $property->property_type->value,
            'url' => route('properties.show', $property),
        ];
        if ($user->canViewPropertyFinancials()) {
            $result['financials'] = [
                'all_in_amount' => $property->all_in_amount,
                'expected_sales_price' => $property->expected_sales_price,
                'expected_profit' => $property->expected_profit,
            ];
        }

        return $result;
    }

    private function searchBuyers(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(['buyer', 'investor', 'builder', 'developer'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $types = filled($data['type'] ?? null)
            ? [$data['type']]
            : [ContactType::Buyer->value, ContactType::Investor->value, ContactType::Builder->value, ContactType::Developer->value];
        $records = Contact::query()->whereIn('type', $types)
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('company', 'like', "%{$search}%")))
            ->limit($data['limit'] ?? 20)->get();

        return ['count' => $records->count(), 'records' => $records->map(fn (Contact $contact): array => [
            'id' => $contact->id, 'name' => $contact->full_name, 'company' => $contact->company,
            'type' => $contact->type->value, 'url' => route('contacts.show', $contact),
        ])->all()];
    }

    private function analyzeData(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, ['report' => ['required', Rule::in(['pipeline_summary', 'overdue_tasks', 'financial_summary'])], 'filters' => ['nullable', 'array']]);

        return match ($data['report']) {
            'pipeline_summary' => ['report' => 'pipeline_summary', 'stages' => Property::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all()],
            'overdue_tasks' => ['report' => 'overdue_tasks', 'count' => Task::query()->open()->where('due_at', '<', now())->count()],
            'financial_summary' => $user->canViewPropertyFinancials()
                ? ['report' => 'financial_summary', 'totals' => Property::query()->whereIn('status', PropertyStatus::portfolioValueStatuses())->selectRaw('COALESCE(SUM(expected_sales_price),0) value, COALESCE(SUM(all_in_amount),0) all_in, COALESCE(SUM(expected_profit),0) profit')->first()->toArray()]
                : throw new AuthorizationException('You cannot access property financial summaries.'),
        };
    }

    private function changePipelineStage(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, ['property_id' => ['required', 'integer'], 'status' => ['required', Rule::enum(PropertyStatus::class)]]);
        $property = Property::query()->findOrFail($data['property_id']);
        Gate::forUser($user)->authorize('update', $property);
        $property = $this->pipelineService->move($property, PropertyStatus::from($data['status']), $user);

        return ['property_id' => $property->id, 'status' => $property->status->value, 'url' => route('properties.show', $property)];
    }

    private function updateProperty(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, ['property_id' => ['required', 'integer'], 'changes' => ['required', 'array', 'min:1']]);
        $property = Property::query()->findOrFail($data['property_id']);
        Gate::forUser($user)->authorize('update', $property);
        $allowed = [
            'parcel_id', 'county', 'address', 'city', 'state', 'postal_code', 'property_type', 'status',
            'acreage', 'zoning', 'flood_zone', 'wetlands_status', 'road_access', 'legal_description',
            'research_notes', 'owner_contact_id', 'document_drive_url', 'closing_documents_url',
            'purchase_price', 'taxes', 'attorney_fees', 'realtor_fees', 'other_costs',
            'arv', 'wholesale_price', 'investor_price', 'expected_sales_price', 'actual_sales_price',
        ];
        $unknown = array_diff(array_keys($data['changes']), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['changes' => 'Unsupported property fields: '.implode(', ', $unknown)]);
        }
        $financial = ['purchase_price', 'taxes', 'attorney_fees', 'realtor_fees', 'other_costs', 'arv', 'wholesale_price', 'investor_price', 'expected_sales_price', 'actual_sales_price'];
        if (! $user->canViewPropertyFinancials() && array_intersect(array_keys($data['changes']), $financial)) {
            throw new AuthorizationException('You cannot update property financial fields.');
        }
        if (! $user->canViewPropertySourceDocuments() && array_intersect(array_keys($data['changes']), ['document_drive_url', 'closing_documents_url'])) {
            throw new AuthorizationException('You cannot update private property document links.');
        }
        $money = ['nullable', 'numeric', 'min:0', 'max:999999999999.99'];
        $changes = $this->validate($data['changes'], [
            'parcel_id' => ['sometimes', 'nullable', 'string', 'max:120'], 'county' => ['sometimes', 'required', 'string', 'max:120'],
            'address' => ['sometimes', 'required', 'string', 'max:255'], 'city' => ['sometimes', 'required', 'string', 'max:120'],
            'state' => ['sometimes', 'required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'], 'postal_code' => ['sometimes', 'nullable', 'regex:/^\d{5}(?:-\d{4})?$/'],
            'property_type' => ['sometimes', Rule::enum(PropertyType::class)], 'status' => ['sometimes', Rule::enum(PropertyStatus::class)],
            'acreage' => ['sometimes', 'nullable', 'numeric', 'min:0'], 'zoning' => ['sometimes', 'nullable', 'string', 'max:120'],
            'flood_zone' => ['sometimes', 'nullable', 'string', 'max:120'], 'wetlands_status' => ['sometimes', Rule::enum(WetlandsStatus::class)],
            'road_access' => ['sometimes', 'nullable', 'string', 'max:160'], 'legal_description' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'research_notes' => ['sometimes', 'nullable', 'string', 'max:50000'], 'owner_contact_id' => ['sometimes', 'nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'document_drive_url' => ['sometimes', 'nullable', 'url:https', 'max:2048'], 'closing_documents_url' => ['sometimes', 'nullable', 'url:https', 'max:2048'],
            'purchase_price' => ['sometimes', ...$money], 'taxes' => ['sometimes', ...$money], 'attorney_fees' => ['sometimes', ...$money],
            'realtor_fees' => ['sometimes', ...$money], 'other_costs' => ['sometimes', ...$money], 'arv' => ['sometimes', ...$money],
            'wholesale_price' => ['sometimes', ...$money], 'investor_price' => ['sometimes', ...$money],
            'expected_sales_price' => ['sometimes', ...$money], 'actual_sales_price' => ['sometimes', ...$money],
        ]);
        if (isset($changes['state'])) {
            $changes['state'] = strtoupper($changes['state']);
        }
        $base = [
            'parcel_id' => $property->parcel_id, 'county' => $property->county, 'address' => $property->address,
            'city' => $property->city, 'state' => $property->state, 'postal_code' => $property->postal_code,
            'property_type' => $property->property_type->value, 'status' => $property->status->value,
            'acreage' => $property->acreage, 'zoning' => $property->zoning, 'flood_zone' => $property->flood_zone,
            'wetlands_status' => $property->wetlands_status->value, 'road_access' => $property->road_access,
            'utilities' => $property->utilities, 'gis_links_text' => implode("\n", $property->gis_links ?? []),
            'document_drive_url' => $property->document_drive_url, 'closing_documents_url' => $property->closing_documents_url,
            'owner_contact_id' => $property->owner_contact_id, 'purchase_price' => $property->purchase_price,
            'arv' => $property->arv, 'wholesale_price' => $property->wholesale_price, 'investor_price' => $property->investor_price,
            'taxes' => $property->taxes, 'attorney_fees' => $property->attorney_fees, 'realtor_fees' => $property->realtor_fees,
            'other_costs' => $property->other_costs, 'expected_sales_price' => $property->expected_sales_price,
            'actual_sales_price' => $property->actual_sales_price, 'legal_description' => $property->legal_description,
            'research_notes' => $property->research_notes,
        ];
        $updated = [...$base, ...$changes];
        if (filled($updated['parcel_id']) && Property::query()->whereKeyNot($property->id)
            ->where('state', $updated['state'])
            ->where('normalized_county', $this->propertyNormalizer->county($updated['county']))
            ->where('normalized_parcel_id', $this->propertyNormalizer->parcelId($updated['parcel_id']))
            ->exists()) {
            throw ValidationException::withMessages(['changes.parcel_id' => 'Another property already uses this parcel ID in the same county and state.']);
        }
        $normalizedAddress = $this->propertyNormalizer->address($updated['address'], $updated['city'], $updated['state'], $updated['postal_code']);
        if (Property::query()->whereKeyNot($property->id)->where('normalized_address', $normalizedAddress)->exists()) {
            throw ValidationException::withMessages(['changes.address' => 'Another property already uses this normalized address.']);
        }
        $property = $this->propertyService->update($property, $updated, $user);

        return ['property_id' => $property->id, 'updated_fields' => array_keys($changes), 'url' => route('properties.show', $property)];
    }

    private function updateChecklist(array $arguments, User $user): array
    {
        $keys = array_map(fn (PropertyChecklistKey $key): string => $key->value, PropertyChecklistKey::cases());
        $data = $this->validate($arguments, [
            'property_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:'.count($keys)],
            'items.*.key' => ['required', Rule::in($keys), 'distinct'],
            'items.*.completed' => ['required', 'boolean'],
            'items.*.evidence_url' => ['nullable', 'url:https', 'max:2048'],
        ]);
        $property = Property::query()->findOrFail($data['property_id']);
        Gate::forUser($user)->authorize('update', $property);
        $current = $this->checklistService->forProperty($property)->mapWithKeys(fn ($item): array => [$item->item_key->value => [
            'completed' => $item->is_completed, 'evidence_url' => $item->evidence_url,
        ]])->all();
        foreach ($data['items'] as $item) {
            if (! $user->canViewPropertySourceDocuments() && array_key_exists('evidence_url', $item)) {
                throw new AuthorizationException('You cannot update private checklist evidence links.');
            }
            $current[$item['key']] = ['completed' => $item['completed'], 'evidence_url' => $item['evidence_url'] ?? $current[$item['key']]['evidence_url']];
        }
        $this->checklistService->update($property, $current, $user, $user->canViewPropertySourceDocuments());

        return ['property_id' => $property->id, 'updated_items' => array_column($data['items'], 'key'), 'url' => route('properties.show', $property)];
    }

    private function createTask(array $arguments, User $user): array
    {
        Gate::forUser($user)->authorize('create', Task::class);
        $data = $this->validate($arguments, [
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)], 'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'due_at' => ['nullable', 'date'], 'subject' => ['nullable', 'regex:/^(contact|property|deal|surplus):[1-9][0-9]*$/'],
        ]);
        if (str_starts_with((string) ($data['subject'] ?? ''), 'surplus:') && ! $user->canViewSurplusCases()) {
            throw new AuthorizationException('You cannot associate tasks with surplus cases.');
        }
        $task = $this->taskService->create([...$data, 'status' => TaskStatus::Pending->value, 'priority' => $data['priority'] ?? TaskPriority::Normal->value, 'recurrence_interval' => 1], $user);

        return ['task_id' => $task->id, 'title' => $task->title, 'url' => route('tasks.show', $task)];
    }

    private function createAuctionEvent(array $arguments, User $user): array
    {
        Gate::forUser($user)->authorize('create', CalendarEvent::class);
        $financialRule = Rule::prohibitedIf(! $user->canViewPropertyFinancials());
        $data = $this->validate($arguments, [
            'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'parcel_number' => ['required', 'string', 'max:120'], 'event_type' => ['required', Rule::in([AuctionEventType::TaxDeedAuction->value, AuctionEventType::ForeclosureAuction->value])],
            'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'],
            'auction_url' => ['required', 'url:https', 'max:2048'], 'property_address' => ['required', 'string', 'max:255'],
            'county' => ['required', Rule::enum(AuctionCounty::class)], 'max_bid' => [$financialRule, 'nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $startsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['time'], config('app.timezone'));
        if (CalendarEvent::query()
            ->where('normalized_parcel_number', $this->propertyNormalizer->parcelId($data['parcel_number']))
            ->where('event_type', $data['event_type'])
            ->where('starts_at', $startsAt)
            ->exists()) {
            throw ValidationException::withMessages(['date' => 'This parcel already has the same auction type scheduled at that date and time.']);
        }
        $event = $this->calendarService->create($data, $user);

        return ['calendar_event_id' => $event->id, 'starts_at' => $event->starts_at->toIso8601String(), 'url' => route('calendar.show', $event)];
    }

    private function searchSurplusCases(array $arguments, User $user): array
    {
        Gate::forUser($user)->authorize('viewAny', SurplusCase::class);
        $data = $this->validate($arguments, [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(SurplusCaseStatus::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $records = SurplusCase::query()->with('claimantContact:id,first_name,last_name')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('case_number', 'like', "%{$search}%")
                ->orWhere('parcel_id', 'like', "%{$search}%")
                ->orWhere('county', 'like', "%{$search}%")
                ->orWhereHas('claimantContact', fn ($contact) => $contact->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByPipelineStatus()->limit($data['limit'] ?? 20)->get();

        return ['count' => $records->count(), 'records' => $records->map(fn (SurplusCase $case) => $this->surplusResult($case, $user))->all()];
    }

    private function getSurplusCase(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, ['surplus_case_id' => ['required', 'integer']]);
        $case = SurplusCase::query()->with(['claimantContact:id,first_name,last_name', 'property:id,address'])->findOrFail($data['surplus_case_id']);
        Gate::forUser($user)->authorize('view', $case);

        return ['record' => $this->surplusResult($case, $user)];
    }

    private function surplusResult(SurplusCase $case, User $user): array
    {
        $result = [
            'id' => $case->id, 'case_number' => $case->case_number, 'status' => $case->status->value,
            'claimant' => $case->claimantContact?->full_name, 'property_id' => $case->property_id,
            'parcel_id' => $case->parcel_id, 'county' => $case->county, 'state' => $case->state,
            'claim_deadline' => $case->claim_deadline?->toDateString(), 'url' => route('surplus.show', $case),
        ];
        if ($user->canViewSurplusFinancials()) {
            $result['financials'] = ['surplus_amount' => $case->surplus_amount, 'agreed_fee_percentage' => $case->agreed_fee_percentage, 'expected_fee' => $case->expected_fee, 'recovered_amount' => $case->recovered_amount, 'actual_fee' => $case->actual_fee];
        }

        return $result;
    }

    private function updateSurplusCase(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, ['surplus_case_id' => ['required', 'integer'], 'changes' => ['required', 'array', 'min:1']]);
        $case = SurplusCase::query()->findOrFail($data['surplus_case_id']);
        Gate::forUser($user)->authorize('update', $case);
        $allowed = ['status', 'claimant_contact_id', 'property_id', 'assigned_user_id', 'source', 'state', 'county', 'parcel_id', 'tax_deed_number', 'foreclosure_case_number', 'certificate_number', 'surplus_amount', 'agreed_fee_percentage', 'recovered_amount', 'actual_fee', 'sale_date', 'claim_deadline', 'agreement_date', 'submitted_at', 'approved_at', 'paid_at', 'document_drive_url', 'notes'];
        if ($unknown = array_diff(array_keys($data['changes']), $allowed)) {
            throw ValidationException::withMessages(['changes' => 'Unsupported surplus fields: '.implode(', ', $unknown)]);
        }
        $financial = ['surplus_amount', 'agreed_fee_percentage', 'recovered_amount', 'actual_fee'];
        if (! $user->canViewSurplusFinancials() && array_intersect(array_keys($data['changes']), $financial)) {
            throw new AuthorizationException('You cannot update surplus financial fields.');
        }
        if (! $user->canViewPropertySourceDocuments() && array_key_exists('document_drive_url', $data['changes'])) {
            throw new AuthorizationException('You cannot update private surplus document links.');
        }
        $money = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'];
        $changes = $this->validate($data['changes'], [
            'status' => ['sometimes', Rule::enum(SurplusCaseStatus::class)],
            'claimant_contact_id' => ['sometimes', 'nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'property_id' => ['sometimes', 'nullable', 'integer', Rule::exists('properties', 'id')->whereNull('deleted_at')],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'source' => ['sometimes', 'nullable', 'string', 'max:120'], 'state' => ['sometimes', 'nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'county' => ['sometimes', 'nullable', 'string', 'max:120'], 'parcel_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tax_deed_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'foreclosure_case_number' => ['sometimes', 'nullable', 'string', 'max:120'], 'certificate_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'surplus_amount' => $money, 'agreed_fee_percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:'.SurplusCaseService::MAX_FEE_PERCENTAGE], 'recovered_amount' => $money, 'actual_fee' => $money,
            'sale_date' => ['sometimes', 'nullable', 'date'], 'claim_deadline' => ['sometimes', 'nullable', 'date'], 'agreement_date' => ['sometimes', 'nullable', 'date'],
            'submitted_at' => ['sometimes', 'nullable', 'date'], 'approved_at' => ['sometimes', 'nullable', 'date'], 'paid_at' => ['sometimes', 'nullable', 'date'],
            'document_drive_url' => ['sometimes', 'nullable', 'url:https', 'max:2048'], 'notes' => ['sometimes', 'nullable', 'string', 'max:50000'],
        ]);
        if (isset($changes['state'])) {
            $changes['state'] = strtoupper($changes['state']);
        }
        $effectiveStatus = SurplusCaseStatus::tryFrom($changes['status'] ?? $case->status->value);
        $claimantId = array_key_exists('claimant_contact_id', $changes) ? $changes['claimant_contact_id'] : $case->claimant_contact_id;
        if ($effectiveStatus && ! in_array($effectiveStatus, [SurplusCaseStatus::Research, SurplusCaseStatus::LocateOwner], true) && ! $claimantId) {
            throw ValidationException::withMessages(['changes.claimant_contact_id' => 'A claimant is required after the Locate Owner stage.']);
        }
        $state = $changes['state'] ?? $case->state; $county = $changes['county'] ?? $case->county; $foreclosure = $changes['foreclosure_case_number'] ?? $case->foreclosure_case_number;
        $taxDeed = $changes['tax_deed_number'] ?? $case->tax_deed_number;
        if ($state && $county && $taxDeed && SurplusCase::query()->whereKeyNot($case->id)->where(compact('state', 'county'))->where('tax_deed_number', $taxDeed)->exists()) {
            throw ValidationException::withMessages(['changes.tax_deed_number' => 'Another surplus case uses this tax deed number in the same county and state.']);
        }
        if ($state && $county && $foreclosure && SurplusCase::query()->whereKeyNot($case->id)->where(compact('state', 'county'))->where('foreclosure_case_number', $foreclosure)->exists()) {
            throw ValidationException::withMessages(['changes.foreclosure_case_number' => 'Another surplus case uses this foreclosure case number in the same county and state.']);
        }
        $case = $this->surplusCaseService->update($case, $changes, $user);

        return ['surplus_case_id' => $case->id, 'updated_fields' => array_keys($changes), 'url' => route('surplus.show', $case)];
    }

    private function searchSops(array $arguments, User $user): array
    {
        Gate::forUser($user)->authorize('viewAny', Sop::class);
        $data = $this->validate($arguments, [
            'search' => ['nullable', 'string', 'max:120'], 'department' => ['nullable', Rule::enum(SopDepartment::class)],
            'status' => ['nullable', Rule::enum(SopStatus::class)], 'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $records = Sop::query()->with('owner:id,name')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('title', 'like', "%{$search}%")->orWhere('summary', 'like', "%{$search}%")->orWhere('content_text', 'like', "%{$search}%")))
            ->when($data['department'] ?? null, fn ($query, $department) => $query->where('department', $department))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("case status when 'active' then 1 when 'draft' then 2 when 'retired' then 3 else 4 end")
            ->orderBy('title')->limit($data['limit'] ?? 10)->get();

        return ['count' => $records->count(), 'records' => $records->map(fn (Sop $sop): array => [
            'id' => $sop->id, 'title' => $sop->title, 'department' => $sop->department->value,
            'status' => $sop->status->value, 'version' => $sop->version_label, 'owner' => $sop->owner?->name,
            'summary' => $sop->summary, 'procedure_excerpt' => Str::limit((string) $sop->content_text, 4000),
            'url' => route('sops.show', $sop),
        ])->all()];
    }

    private function getSop(array $arguments, User $user): array
    {
        $data = $this->validate($arguments, ['sop_id' => ['required', 'integer']]);
        $sop = Sop::query()->with('owner:id,name')->findOrFail($data['sop_id']);
        Gate::forUser($user)->authorize('view', $sop);

        return ['record' => [
            'id' => $sop->id, 'title' => $sop->title, 'department' => $sop->department->value,
            'status' => $sop->status->value, 'version' => $sop->version_label, 'owner' => $sop->owner?->name,
            'summary' => $sop->summary, 'procedure' => Str::limit((string) $sop->content_text, 30000),
            'effective_date' => $sop->effective_date?->toDateString(), 'review_date' => $sop->review_date?->toDateString(),
            'has_private_file' => $sop->hasFile(), 'has_drive_source' => filled($sop->drive_url), 'url' => route('sops.show', $sop),
        ]];
    }

    private function validate(array $arguments, array $rules): array
    {
        return Validator::make($arguments, $rules)->validate();
    }
}
