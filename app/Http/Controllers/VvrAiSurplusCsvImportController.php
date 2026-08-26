<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveVvrAiSurplusCsvImportRequest;
use App\Http\Requests\StoreVvrAiSurplusCsvImportRequest;
use App\Models\AiConversation;
use App\Models\AiSurplusCsvImport;
use App\Services\VvrAiSurplusCsvImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VvrAiSurplusCsvImportController extends Controller
{
    public function store(
        StoreVvrAiSurplusCsvImportRequest $request,
        VvrAiSurplusCsvImportService $service,
    ): RedirectResponse {
        $prompt = $request->string('prompt')->toString() ?: 'Import these contacts and Surplus cases from the attached CSV.';
        $conversation = AiConversation::query()->create([
            'token' => (string) Str::uuid(), 'user_id' => $request->user()->id,
            'title' => Str::limit($prompt, 80), 'intent' => 'create_surplus_contacts_from_csv',
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
                'content' => 'I parsed '.$import->row_count.' CSV row(s) locally, found '.$review['valid_rows'].' valid row(s), '.$review['invalid_rows'].' invalid row(s), and '.$review['duplicate_cases'].' existing Surplus case match(es). Review the jurisdiction and rows before approval. No property addresses were inferred from mailing addresses.',
                'metadata_json' => ['surplus_csv_import_token' => $import->token],
            ]);
        } catch (ValidationException $exception) {
            $conversation->update(['status' => 'failed', 'last_message_at' => now()]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => collect($exception->errors())->flatten()->first() ?: 'The CSV could not be processed.']);

            return redirect()->route('vvr-ai.conversations.show', $conversation)->withErrors($exception->errors());
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $conversation->update(['status' => 'failed', 'last_message_at' => now()]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => 'The CSV could not be processed. Nothing was changed.']);

            return redirect()->route('vvr-ai.conversations.show', $conversation)
                ->withErrors(['csv_file' => 'The CSV could not be processed. Nothing was changed.']);
        }

        return redirect()->route('vvr-ai.conversations.show', $conversation)
            ->with('success', 'VVR AI prepared the CSV import for review.');
    }

    public function approve(
        ApproveVvrAiSurplusCsvImportRequest $request,
        AiConversation $conversation,
        AiSurplusCsvImport $import,
        VvrAiSurplusCsvImportService $service,
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
                ->withErrors(['approval' => 'The CSV import failed and every related CRM write was rolled back.']);
        }

        return redirect()->route('vvr-ai.conversations.show', $conversation)
            ->with('success', 'The approved CSV contacts and Surplus cases were created successfully.');
    }
}
