<?php

namespace App\Http\Controllers;

use App\Enums\AuctionCounty;
use App\Enums\AuctionEventType;
use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Property;
use App\Services\CalendarEventService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CalendarEventController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CalendarEvent::class);
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'county' => ['nullable', Rule::enum(AuctionCounty::class)],
            'event_type' => ['nullable', Rule::enum(AuctionEventType::class)],
        ]);
        $month = filled($validated['month'] ?? null)
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'], config('app.timezone'))
            : now()->toImmutable()->startOfMonth();
        $gridStart = $month->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY);

        $query = CalendarEvent::query()
            ->with('property:id,address,city,state,postal_code')
            ->when($validated['county'] ?? null, fn ($query, $county) => $query->where('county', $county))
            ->when($validated['event_type'] ?? null, fn ($query, $type) => $query->where('event_type', $type));
        $events = (clone $query)->whereBetween('starts_at', [$gridStart, $gridEnd])->orderBy('starts_at')->get();
        $eventsByDate = $events->groupBy(fn (CalendarEvent $event): string => $event->starts_at->format('Y-m-d'));
        $days = collect(range(0, $gridStart->diffInDays($gridEnd)))->map(
            fn (int $offset): CarbonImmutable => $gridStart->addDays($offset)
        );
        $upcoming = (clone $query)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(12)->get();

        return view('calendar.index', compact('month', 'days', 'eventsByDate', 'upcoming'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', CalendarEvent::class);
        $property = $request->filled('property') ? Property::query()->find($request->integer('property')) : null;

        return view('calendar.create', [
            'defaults' => $this->propertyDefaults($property),
            ...$this->formData(),
        ]);
    }

    public function store(StoreCalendarEventRequest $request, CalendarEventService $service): RedirectResponse
    {
        $event = $service->create($request->validated(), $request->user());

        return redirect()->route('calendar.show', $event)->with('success', 'Calendar event scheduled.');
    }

    public function show(CalendarEvent $event): View
    {
        Gate::authorize('view', $event);
        $event->load('property:id,address,city,state,postal_code,parcel_id');

        return view('calendar.show', compact('event'));
    }

    public function edit(CalendarEvent $event): View
    {
        Gate::authorize('update', $event);

        return view('calendar.edit', ['event' => $event, 'defaults' => [], ...$this->formData()]);
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $event, CalendarEventService $service): RedirectResponse
    {
        $service->update($event, $request->validated(), $request->user());

        return redirect()->route('calendar.show', $event)->with('success', 'Calendar event updated.');
    }

    public function destroy(Request $request, CalendarEvent $event, CalendarEventService $service): RedirectResponse
    {
        Gate::authorize('delete', $event);
        $service->delete($event, $request->user());

        return redirect()->route('calendar.index')->with('success', 'Calendar event archived.');
    }

    private function formData(): array
    {
        return [
            'eventTypes' => AuctionEventType::cases(),
            'counties' => AuctionCounty::cases(),
            'properties' => Property::query()->orderBy('state')->orderBy('city')->orderBy('address')
                ->get(['id', 'parcel_id', 'address', 'city', 'state', 'postal_code', 'county']),
        ];
    }

    private function propertyDefaults(?Property $property): array
    {
        if (! $property) {
            return [];
        }

        $county = str($property->county)->replaceMatches('/\s+County$/i', '')->lower()->toString();

        return [
            'property_id' => $property->id,
            'parcel_number' => $property->parcel_id,
            'property_address' => $property->full_address,
            'county' => AuctionCounty::tryFrom($county)?->value,
        ];
    }
}
