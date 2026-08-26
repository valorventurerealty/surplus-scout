<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Enums\PhoneInteractionDirection;
use App\Enums\PhoneInteractionMatchStatus;
use App\Enums\PhoneInteractionType;
use App\Models\Contact;
use App\Models\PhoneInteraction;
use App\Models\AuditLog;
use App\Http\Requests\LinkPhoneInteractionContactRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PhoneInteractionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PhoneInteraction::class);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'event_type' => ['nullable', Rule::enum(PhoneInteractionType::class)],
            'direction' => ['nullable', Rule::enum(PhoneInteractionDirection::class)],
            'match_status' => ['nullable', Rule::enum(PhoneInteractionMatchStatus::class)],
        ]);

        $accessible = PhoneInteraction::query()
            ->when(! $request->user()->canViewSurplusCases(), fn (Builder $query) => $query
                ->whereNotNull('contact_id')
                ->whereNotIn('contact_id', Contact::query()->select('id')->whereIn('type', [
                    ContactType::Surplus->value,
                    ContactType::PreTaxAuctions->value,
                ])));

        $metrics = [
            'today' => (clone $accessible)->whereBetween('occurred_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'unmatched' => (clone $accessible)->where('match_status', '!=', PhoneInteractionMatchStatus::Matched->value)->count(),
            'inbound' => (clone $accessible)->where('direction', PhoneInteractionDirection::Inbound->value)->count(),
            'total' => (clone $accessible)->count(),
        ];

        $interactions = $accessible
            ->with('contact:id,first_name,last_name,company,type')
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('caller_name', 'like', "%{$search}%")
                    ->orWhere('caller_phone', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn (Builder $query) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%"));
            }))
            ->when($validated['event_type'] ?? null, fn (Builder $query, string $type) => $query->where('event_type', $type))
            ->when($validated['direction'] ?? null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when($validated['match_status'] ?? null, fn (Builder $query, string $status) => $query->where('match_status', $status))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('phone-interactions.index', compact('interactions', 'metrics'));
    }

    public function show(PhoneInteraction $phoneInteraction): View
    {
        $phoneInteraction->load('contact');
        Gate::authorize('view', $phoneInteraction);

        $contacts = collect();
        if (Gate::allows('update', $phoneInteraction)) {
            $contacts = Contact::query()
                ->when(! request()->user()->canViewSurplusCases(), fn (Builder $query) => $query->whereNotIn('type', [
                    ContactType::Surplus->value,
                    ContactType::PreTaxAuctions->value,
                ]))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'company', 'phone', 'type']);
        }

        return view('phone-interactions.show', ['interaction' => $phoneInteraction, 'contacts' => $contacts]);
    }

    public function linkContact(LinkPhoneInteractionContactRequest $request, PhoneInteraction $phoneInteraction): RedirectResponse
    {
        $contact = $request->selectedContact();
        Gate::authorize('view', $contact);
        $oldContactId = $phoneInteraction->contact_id;

        $phoneInteraction->update([
            'contact_id' => $contact->id,
            'match_status' => PhoneInteractionMatchStatus::Matched,
        ]);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'event' => 'phone_contact_linked',
            'auditable_type' => $phoneInteraction->getMorphClass(),
            'auditable_id' => $phoneInteraction->id,
            'old_values' => ['contact_id' => $oldContactId],
            'new_values' => ['contact_id' => $contact->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('phone-interactions.show', $phoneInteraction)->with('success', 'Phone activity linked to '.$contact->full_name.'.');
    }
}
