<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveVvrAiPreAuctionCsvImportRequest;
use App\Http\Requests\StoreVvrAiPreAuctionCsvImportRequest;
use App\Models\AiConversation;
use App\Models\AiPreAuctionCsvImport;
use App\Services\VvrAiPreAuctionCsvImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VvrAiPreAuctionCsvImportController extends Controller
{
    public function store(StoreVvrAiPreAuctionCsvImportRequest $request, VvrAiPreAuctionCsvImportService $service): RedirectResponse
    {
        $prompt = $request->string('prompt')->toString() ?: 'Create or update PreTax Auctions contacts and acquisition files from this CSV.';
        $conversation = AiConversation::query()->create([
            'token' => (string) Str::uuid(), 'user_id' => $request->user()->id,
            'title' => Str::limit($prompt, 80), 'intent' => 'create_pre_auction_acquisitions_from_csv',
            'status' => 'processing', 'last_message_at' => now(),
        ]);
        $conversation->messages()->create([
            'user_id' => $request->user()->id, 'role' => 'user', 'content' => $prompt,
            'metadata_json' => ['has_csv' => true, 'document_name' => $request->file('csv_file')->getClientOriginalName()],
        ]);

        try {
            $import = $service->prepare($request->file('csv_file'), $prompt, $request->user(), $conversation);
            $review = $service->review($import, $request->user());
            $conversation->update(['status' => 'awaiting_approval', 'last_message_at' => now()]);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'I parsed '.$import->row_count.' row(s) privately and found '.$review['valid_rows'].' valid row(s), '.$review['invalid_rows'].' invalid row(s), '.$review['duplicate_cases'].' existing PreTax Auction match(es), and '.$review['duplicate_contacts'].' reusable contact(s). Review the exact changes before approval. Mailing addresses will not be used as property addresses, and assessor values will not be treated as surplus.',
                'metadata_json' => ['pre_auction_csv_import_token' => $import->token],
            ]);
        } catch (ValidationException $exception) {
            $conversation->update(['status' => 'failed', 'last_message_at' => now()]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => collect($exception->errors())->flatten()->first() ?: 'The PreTax Auctions CSV could not be processed.']);
            return redirect()->route('vvr-ai.conversations.show', $conversation)->withErrors($exception->errors());
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $conversation->update(['status' => 'failed', 'last_message_at' => now()]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => 'The PreTax Auctions CSV could not be processed. Nothing was changed.']);
            return redirect()->route('vvr-ai.conversations.show', $conversation)
                ->withErrors(['csv_file' => 'The PreTax Auctions CSV could not be processed. Nothing was changed.']);
        }

        return redirect()->route('vvr-ai.conversations.show', $conversation)
            ->with('success', 'VVR AI prepared the PreTax Auctions CSV import for review.');
    }

    public function approve(
        ApproveVvrAiPreAuctionCsvImportRequest $request,
        AiConversation $conversation,
        AiPreAuctionCsvImport $import,
        VvrAiPreAuctionCsvImportService $service,
    ): RedirectResponse {
        Gate::authorize('view', $conversation);
        abort_unless($import->ai_conversation_id === $conversation->id, 404);
        try {
            $service->execute($import, $request->validated(), $request->user(), $conversation);
        } catch (ValidationException $exception) {
            return redirect()->route('vvr-ai.conversations.show', $conversation)->withInput()->withErrors($exception->errors());
        } catch (Throwable $exception) {
            report($exception);
            return redirect()->route('vvr-ai.conversations.show', $conversation)->withInput()
                ->withErrors(['approval' => 'The PreTax Auctions CSV import failed and every related CRM write was rolled back.']);
        }

        return redirect()->route('vvr-ai.conversations.show', $conversation)
            ->with('success', 'The approved PreTax Auctions contacts and files were created successfully.');
    }
}
