<?php

namespace App\Http\Controllers;

use App\Contracts\AiProviderInterface;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\WetlandsStatus;
use App\Http\Requests\StoreVvrAiRequest;
use App\Http\Requests\ApproveVvrAiSurplusIntakeRequest;
use App\Models\AiActionPlan;
use App\Models\AiConversation;
use App\Models\Contact;
use App\Models\Property;
use App\Services\PropertyIntakeService;
use App\Services\VvrAiActionService;
use App\Services\SurplusIntakeService;
use App\Services\VvrAiSurplusCsvImportService;
use App\Services\VvrAiPreAuctionCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class VvrAiController extends Controller
{
    public function index(AiProviderInterface $provider): View
    {
        Gate::authorize('viewAny', AiConversation::class);

        return view('vvr-ai.index', [
            'aiConfigured' => $provider->isConfigured(),
            'conversations' => $this->conversations(),
        ]);
    }

    public function store(
        StoreVvrAiRequest $request,
        PropertyIntakeService $intakeService,
        SurplusIntakeService $surplusIntakeService,
        AiProviderInterface $provider,
        VvrAiActionService $actionService,
    ): RedirectResponse {
        if (! $provider->isConfigured()) {
            throw ValidationException::withMessages(['prompt' => 'Gemini is not configured on the server.']);
        }

        $prompt = $request->string('prompt')->toString();
        $documentName = (string) $request->file('document')?->getClientOriginalName();
        $surplusIntake = $request->hasFile('document')
            && ($this->looksLikeSurplusIntake($prompt) || preg_match('/\b(trim|tax[ _-]*notice|property[ _-]*card)\b/i', $documentName) === 1);
        $conversation = AiConversation::query()->create([
            'token' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'title' => Str::limit($prompt, 80),
            'intent' => $surplusIntake ? 'create_surplus_case' : 'create_property_from_documents',
            'status' => 'processing',
            'last_message_at' => now(),
        ]);
        $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'role' => 'user',
            'content' => $prompt,
            'metadata_json' => [
                'has_document' => $request->hasFile('document'),
                'document_name' => $request->file('document')?->getClientOriginalName(),
            ],
        ]);

        try {
            if ($surplusIntake) {
                Gate::authorize('create', \App\Models\SurplusCase::class);
                Gate::authorize('create', Property::class);
                Gate::authorize('create', Contact::class);
                if (! $request->user()->canViewPropertySourceDocuments()) {
                    throw new AuthorizationException('You cannot use the private Surplus document intake workflow.');
                }
                $intake = $surplusIntakeService->extract($prompt, $request->file('document'), $request->user(), $conversation);
                $review = $surplusIntakeService->review($intake, $request->user());
                $fieldCount = collect($review['summary']['fields'])->filter(fn (array $field) => filled($field['value'] ?? null))->count();
                $duplicateCount = collect([
                    ...$review['summary']['property_duplicates'], ...$review['summary']['contact_duplicates'],
                    ...$review['summary']['surplus_duplicates'], ...$review['summary']['document_duplicates'],
                ])->count();
                $conversation->update(['status' => 'awaiting_approval', 'last_message_at' => now()]);
                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => "I extracted {$fieldCount} candidate ownership, property, and tax-history fields and found {$duplicateCount} possible duplicates. Review the proposed property, Surplus contact, case, tax record, source attachment, and research tasks before approval. No annual tax was treated as an acquisition cost and no surplus amount was invented.",
                    'metadata_json' => ['surplus_intake_token' => $intake->token],
                ]);

                return redirect()->route('vvr-ai.conversations.show', $conversation)
                    ->with('success', 'VVR AI prepared a Surplus document-intake plan. Review and approve it below.');
            }

            if (! $request->hasFile('document') && ! $this->looksLikePropertyIntake($prompt)) {
                $prepared = $actionService->prepare($conversation, $prompt, $request->user());
                if (! $prepared['property_intake']) {
                    return redirect()->route('vvr-ai.conversations.show', $conversation)
                        ->with('success', $prepared['plan']->status === 'completed'
                            ? 'VVR AI completed the authorized read-only request.'
                            : 'VVR AI prepared an action plan for review.');
                }
            }

            Gate::authorize('create', Property::class);
            if ($request->hasFile('document') && ! $request->user()->canViewPropertySourceDocuments()) {
                throw new AuthorizationException('You cannot use the property document intake workflow.');
            }
            $intake = $intakeService->extractForAssistant(
                $prompt,
                $request->file('document'),
                $request->user(),
                $conversation,
            );
            $review = $intakeService->review($intake, $request->user());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'The request could not be processed.';
            $conversation->update(['status' => 'failed', 'last_message_at' => now()]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => $message]);

            return redirect()->route('vvr-ai.conversations.show', $conversation)->withErrors($exception->errors());
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $message = 'VVR AI could not process this request. Nothing was changed. Please try again or review the server AI configuration.';
            $conversation->update(['status' => 'failed', 'last_message_at' => now()]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => $message]);

            return redirect()->route('vvr-ai.conversations.show', $conversation)->withErrors(['prompt' => $message]);
        }

        $fieldCount = collect($review['summary']['fields'])->filter(fn (array $field) => filled($field['value'] ?? null))->count();
        $missingCount = count($review['summary']['missing_fields']);
        $duplicateCount = count($review['summary']['duplicates']);
        $conversation->update(['status' => 'awaiting_approval', 'last_message_at' => now()]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => "I extracted {$fieldCount} candidate fields, identified {$missingCount} missing fields, and found {$duplicateCount} possible duplicates. Review and correct the proposed property before approval.",
            'metadata_json' => ['intake_token' => $intake->token],
        ]);

        return redirect()->route('vvr-ai.conversations.show', $conversation)
            ->with('success', 'VVR AI prepared a property creation plan. Review and approve it below.');
    }

    public function show(
        AiConversation $conversation,
        PropertyIntakeService $intakeService,
        SurplusIntakeService $surplusIntakeService,
        VvrAiSurplusCsvImportService $csvImportService,
        VvrAiPreAuctionCsvImportService $preAuctionCsvImportService,
        AiProviderInterface $provider,
    ): View {
        Gate::authorize('view', $conversation);
        $conversation->load('messages');
        $propertyIntake = $conversation->propertyIntakes()->latest()->first();
        $surplusIntake = $conversation->surplusIntakes()->latest()->first();
        $csvImport = $conversation->surplusCsvImports()->latest()->first();
        $preAuctionCsvImport = $conversation->preAuctionCsvImports()->latest()->first();
        $intake = $propertyIntake ?? $surplusIntake ?? $csvImport ?? $preAuctionCsvImport;
        $intakeReview = null;
        $surplusIntakeReview = null;
        $surplusCsvImportReview = null;
        $preAuctionCsvImportReview = null;
        $aiValues = [];

        if ($propertyIntake?->status === 'ready') {
            $review = $intakeService->review($propertyIntake, request()->user());
            $intakeReview = [
                'token' => $propertyIntake->token,
                ...$review['summary'],
            ];
            $aiValues = $review['values'];
        }
        if ($surplusIntake?->status === 'ready') {
            $review = $surplusIntakeService->review($surplusIntake, request()->user());
            $surplusIntakeReview = ['token' => $surplusIntake->token, ...$review['summary']];
            $aiValues = $review['values'];
        }
        if ($csvImport && in_array($csvImport->status, ['ready', 'completed'], true)) {
            $surplusCsvImportReview = $csvImportService->review($csvImport, request()->user());
        }
        if ($preAuctionCsvImport && in_array($preAuctionCsvImport->status, ['ready', 'completed'], true)) {
            $preAuctionCsvImportReview = $preAuctionCsvImportService->review($preAuctionCsvImport, request()->user());
        }

        $property = null;
        $propertyId = data_get($conversation->result_json, 'property_id');
        if ($propertyId) {
            $property = Property::query()->find($propertyId);
        }
        $createdContact = Contact::query()->find(data_get($conversation->result_json, 'contact_id'));
        $createdSurplusCase = \App\Models\SurplusCase::query()->find(data_get($conversation->result_json, 'surplus_case_id'));
        $actionPlan = $conversation->actionPlans()->with('toolCalls')->latest()->first();

        return view('vvr-ai.show', [
            'conversation' => $conversation,
            'conversations' => $this->conversations(),
            'intake' => $intake,
            'intakeReview' => $intakeReview,
            'surplusIntakeReview' => $surplusIntakeReview,
            'surplusCsvImportReview' => $surplusCsvImportReview,
            'surplusCsvImportResult' => $csvImport?->status === 'completed' ? $csvImport->result_json : null,
            'preAuctionCsvImportReview' => $preAuctionCsvImportReview,
            'preAuctionCsvImportResult' => $preAuctionCsvImport?->status === 'completed' ? $preAuctionCsvImport->result_json : null,
            'aiValues' => $aiValues,
            'createdProperty' => $property,
            'createdContact' => $createdContact,
            'createdSurplusCase' => $createdSurplusCase,
            'actionPlan' => $actionPlan,
            'aiConfigured' => $provider->isConfigured(),
            ...$this->propertyFormData(),
        ]);
    }

    public function approveSurplusIntake(
        ApproveVvrAiSurplusIntakeRequest $request,
        AiConversation $conversation,
        SurplusIntakeService $service,
    ): RedirectResponse {
        Gate::authorize('view', $conversation);
        try {
            $service->execute($request->validated(), $request->user(), $conversation);
        } catch (ValidationException $exception) {
            return redirect()->route('vvr-ai.conversations.show', $conversation)->withInput()->withErrors($exception->errors());
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('vvr-ai.conversations.show', $conversation)
                ->withInput()->withErrors(['approval' => 'Execution failed and every related CRM write was rolled back.']);
        }

        return redirect()->route('vvr-ai.conversations.show', $conversation)
            ->with('success', 'The approved Surplus intake completed successfully.');
    }

    public function approve(
        AiConversation $conversation,
        AiActionPlan $plan,
        VvrAiActionService $service,
    ): RedirectResponse {
        Gate::authorize('view', $conversation);
        abort_unless($plan->conversation_id === $conversation->id, 404);
        try {
            $service->approve($plan, request()->user());
        } catch (ValidationException $exception) {
            return redirect()->route('vvr-ai.conversations.show', $conversation)->withErrors($exception->errors());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('vvr-ai.conversations.show', $conversation)
                ->withErrors(['approval' => 'Execution failed and all related CRM writes were rolled back.']);
        }

        return redirect()->route('vvr-ai.conversations.show', $conversation)
            ->with('success', 'The approved VVR AI actions were completed successfully.');
    }

    public function reject(
        Request $request,
        AiConversation $conversation,
        AiActionPlan $plan,
        VvrAiActionService $service,
    ): RedirectResponse {
        Gate::authorize('view', $conversation);
        abort_unless($plan->conversation_id === $conversation->id, 404);
        $validated = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:1000']]);
        $service->reject($plan, $request->user(), $validated['rejection_reason'] ?? null);

        return redirect()->route('vvr-ai.conversations.show', $conversation)
            ->with('success', 'The VVR AI plan was rejected. No CRM changes were made.');
    }

    private function conversations()
    {
        return AiConversation::query()
            ->where('user_id', auth()->id())
            ->latest('last_message_at')
            ->limit(25)
            ->get();
    }

    private function looksLikePropertyIntake(string $prompt): bool
    {
        return preg_match('/\b(i\s+(?:bought|purchased|acquired)\s+(?:this|a)\s+property|(?:create|add|prepare)\s+(?:this|a|the)?\s*property)\b/i', $prompt) === 1;
    }

    private function looksLikeSurplusIntake(string $prompt): bool
    {
        return preg_match('/\b(surplus|trim\s+notice|tax\s+notice|prior[- ]year\s+tax|tax\s+history)\b/i', $prompt) === 1;
    }

    private function propertyFormData(): array
    {
        return [
            'propertyTypes' => PropertyType::cases(),
            'propertyStatuses' => PropertyStatus::cases(),
            'wetlandsStatuses' => WetlandsStatus::cases(),
            'contacts' => Contact::query()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company']),
        ];
    }
}
