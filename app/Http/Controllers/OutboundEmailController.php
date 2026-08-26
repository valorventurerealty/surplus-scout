<?php

namespace App\Http\Controllers;

use App\Enums\ArmoryEmailTemplateStatus;
use App\Enums\OutboundEmailStatus;
use App\Http\Requests\OutboundEmailRequest;
use App\Models\ArmoryEmailTemplate;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\EmailSignature;
use App\Models\OutboundEmail;
use App\Models\Property;
use App\Enums\ContactType;
use App\Models\PreAuctionAcquisition;
use App\Models\SurplusCase;
use App\Services\EmailContextResolver;
use App\Services\OutboundEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OutboundEmailController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', OutboundEmail::class);
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::enum(OutboundEmailStatus::class)]]);
        $emails = OutboundEmail::query()->with(['user:id,name', 'primaryContact:id,first_name,last_name'])
            ->when(! $request->user()->canViewAllOutboundEmails(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when(! $request->user()->canViewSurplusCases(), fn ($q) => $q->where(fn ($q) => $q->whereNull('related_type')->orWhere('related_type', '!=', SurplusCase::class))->whereDoesntHave('primaryContact', fn ($q) => $q->where('type', ContactType::Surplus->value)))
            ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('related_type')->orWhere('related_type', '!=', PreAuctionAcquisition::class))
                ->whereDoesntHave('primaryContact', fn ($q) => $q->where('type', ContactType::PreTaxAuctions->value)))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('subject', 'like', "%{$search}%")->orWhere('from_address', 'like', "%{$search}%")))
            ->latest()->paginate(25)->withQueryString();
        return view('email.index', ['emails' => $emails, 'statuses' => OutboundEmailStatus::cases()]);
    }

    public function create(Request $request, EmailContextResolver $resolver): View
    {
        Gate::authorize('create', OutboundEmail::class);
        $related = $resolver->resolve($request->string('related_type')->toString() ?: null, $request->integer('related_id') ?: null, $request->user());
        $contact = $resolver->contact($related);
        return view('email.create', $this->formData($request) + ['related' => $related, 'relatedType' => $resolver->type($related), 'selectedContact' => $contact]);
    }

    public function store(OutboundEmailRequest $request, OutboundEmailService $service): RedirectResponse
    {
        try {
            $email = $service->create($request->validated(), $request->file('attachments', []), $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('email.create')
                ->withErrors($exception->errors())
                ->withInput($request->except('attachments'));
        }

        return redirect()->route('email.show', $email)->with('success', 'Draft saved. Review it before sending.');
    }

    public function show(OutboundEmail $outboundEmail, OutboundEmailService $service): View
    {
        Gate::authorize('view', $outboundEmail);
        $outboundEmail->load(['user:id,name', 'primaryContact:id,first_name,last_name,email', 'attachments', 'related', 'signature']);
        $preview = $service->preview($outboundEmail);
        return view('email.show', ['email' => $outboundEmail, 'previewSubject' => $outboundEmail->final_text ? $outboundEmail->subject : $preview['subject'], 'previewBody' => $outboundEmail->final_text ?: $preview['text'], 'previewHtml' => $outboundEmail->final_html ?: $preview['html'], 'unresolved' => $outboundEmail->final_text ? [] : $preview['unresolved'], 'reviewFingerprint' => $preview['fingerprint']]);
    }

    public function edit(OutboundEmail $outboundEmail): View
    {
        Gate::authorize('update', $outboundEmail);
        return view('email.edit', $this->formData(request()) + ['email' => $outboundEmail->load(['related', 'attachments']), 'related' => $outboundEmail->related, 'relatedType' => app(EmailContextResolver::class)->type($outboundEmail->related), 'selectedContact' => $outboundEmail->primaryContact]);
    }

    public function update(OutboundEmailRequest $request, OutboundEmail $outboundEmail, OutboundEmailService $service): RedirectResponse
    {
        try {
            $service->update($outboundEmail, $request->validated(), $request->file('attachments', []), $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('email.edit', $outboundEmail)
                ->withErrors($exception->errors())
                ->withInput($request->except('attachments'));
        }

        return redirect()->route('email.show', $outboundEmail)->with('success', 'Draft updated.');
    }

    public function send(Request $request, OutboundEmail $outboundEmail, OutboundEmailService $service): RedirectResponse
    {
        Gate::authorize('send', $outboundEmail);
        $validated = $request->validate(['confirm_send' => ['accepted'], 'review_fingerprint' => ['required', 'string', 'size:64']]);
        $service->queue($outboundEmail, $request->user(), $validated['review_fingerprint']);
        return redirect()->route('email.show', $outboundEmail)->with('success', 'Email queued for secure delivery.');
    }

    public function cancel(Request $request, OutboundEmail $outboundEmail, OutboundEmailService $service): RedirectResponse
    {
        Gate::authorize('cancel', $outboundEmail);
        $service->cancel($outboundEmail);
        return back()->with('success', 'Queued email cancelled before delivery.');
    }

    public function retry(Request $request, OutboundEmail $outboundEmail, OutboundEmailService $service): RedirectResponse
    {
        Gate::authorize('retry', $outboundEmail);
        $service->retry($outboundEmail, $request->user());
        return back()->with('success', 'Email queued for one manual retry.');
    }

    public function destroy(OutboundEmail $outboundEmail, OutboundEmailService $service): RedirectResponse
    {
        Gate::authorize('delete', $outboundEmail);
        $service->deleteDraft($outboundEmail);
        return redirect()->route('email.index')->with('success', 'Draft deleted. It will be permanently purged after the retention period.');
    }

    private function formData(Request $request): array
    {
        $user = $request->user();
        $relatedOptions = collect();

        Property::query()->orderBy('address')->limit(500)->get(['id', 'address', 'city', 'state', 'parcel_id'])
            ->filter(fn (Property $property): bool => $user->can('view', $property))
            ->each(fn (Property $property) => $relatedOptions->push([
                'value' => 'property:'.$property->id,
                'label' => 'Property — '.$property->address.' — '.($property->parcel_id ?: 'No parcel ID'),
            ]));

        Deal::query()->orderBy('deal_number')->limit(500)->get(['id', 'token', 'deal_number', 'title'])
            ->filter(fn (Deal $deal): bool => $user->can('view', $deal))
            ->each(fn (Deal $deal) => $relatedOptions->push([
                'value' => 'deal:'.$deal->id,
                'label' => 'Deal — '.$deal->deal_number.' — '.$deal->title,
            ]));

        if ($user->canViewSurplusCases()) {
            SurplusCase::query()->with('claimantContact:id,first_name,last_name')->orderBy('case_number')->limit(500)->get(['id', 'token', 'case_number', 'claimant_contact_id', 'parcel_id', 'county'])
                ->filter(fn (SurplusCase $case): bool => $user->can('view', $case))
                ->each(fn (SurplusCase $case) => $relatedOptions->push([
                    'value' => 'surplus:'.$case->id,
                    'label' => 'Surplus — '.$case->case_number.' — '.($case->parcel_id ?: 'No parcel ID').' — '.($case->claimantContact?->full_name ?: 'No claimant'),
                ]));
        }

        if ($user->canViewPreAuctionAcquisitions()) {
            PreAuctionAcquisition::query()->with('ownerContact:id,first_name,last_name')->orderBy('case_number')->limit(500)->get(['id', 'token', 'case_number', 'owner_contact_id', 'parcel_id', 'county'])
                ->filter(fn (PreAuctionAcquisition $case): bool => $user->can('view', $case))
                ->each(fn (PreAuctionAcquisition $case) => $relatedOptions->push([
                    'value' => 'pre_auction:'.$case->id,
                    'label' => 'PreTax Auction — '.$case->case_number.' — '.($case->parcel_id ?: 'No parcel ID').' — '.($case->ownerContact?->full_name ?: 'No owner'),
                ]));
        }

        return [
            'contacts' => Contact::query()->whereNotNull('email')
                ->when(! $request->user()->canViewSurplusCases(), fn ($query) => $query->where('type', '!=', ContactType::Surplus->value))
                ->when(! $request->user()->canViewPreAuctionAcquisitions(), fn ($query) => $query->where('type', '!=', ContactType::PreTaxAuctions->value))
                ->orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']),
            'templates' => ArmoryEmailTemplate::query()->with('attachments:id,armory_email_template_id,original_name,size_bytes')->where('status', ArmoryEmailTemplateStatus::Active)->orderBy('name')->get(['id', 'name', 'subject', 'body_text']),
            'signatures' => EmailSignature::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'mergeTags' => config('email.merge_fields'),
            'relatedOptions' => $relatedOptions,
        ];
    }
}
