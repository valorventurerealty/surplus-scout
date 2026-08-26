<?php

namespace App\Services;

use App\Contracts\PropertyDocumentExtractionInterface;
use App\Data\PropertyExtractionResult;
use App\Domain\Properties\PropertyNormalizer;
use App\Models\Property;
use App\Models\PropertyIntakeFile;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PropertyIntakeService
{
    public function __construct(
        private readonly PropertyDocumentExtractionInterface $extractor,
        private readonly PropertyNormalizer $normalizer,
    ) {}

    public function extract(UploadedFile $upload, User $user): PropertyIntakeFile
    {
        $hash = hash_file('sha256', $upload->getRealPath());
        $existing = PropertyIntakeFile::query()
            ->where('user_id', $user->id)
            ->where('sha256', $hash)
            ->whereIn('status', ['ready', 'attached'])
            ->latest()
            ->first();

        if ($existing?->status === 'attached') {
            throw ValidationException::withMessages([
                'document' => 'This exact file is already attached to property #'.$existing->property_id.'.',
            ]);
        }

        if ($existing && $existing->expires_at?->isFuture() && Storage::disk($existing->disk)->exists($existing->path)) {
            return $existing;
        }

        $token = (string) Str::uuid();
        $extension = strtolower($upload->getClientOriginalExtension());
        $path = 'property-intakes/'.$user->id.'/'.$token.'.'.$extension;

        if (! Storage::disk('local')->put($path, $upload->getContent())) {
            throw ValidationException::withMessages(['document' => 'The document could not be saved to private storage.']);
        }

        try {
            $intake = PropertyIntakeFile::query()->create([
                'token' => $token,
                'user_id' => $user->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => Str::limit(basename($upload->getClientOriginalName()), 255, ''),
                'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $upload->getSize(),
                'sha256' => $hash,
                'status' => 'processing',
                'provider' => config('ai.provider'),
                'model' => config('ai.extraction_model'),
                'expires_at' => now()->addHours((int) config('ai.property_intake_expiration_hours', 24)),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        try {
            $result = $this->extractor->extract($intake);
            $intake->update([
                'status' => 'ready',
                'provider_response_id' => $result->responseId,
                'extraction_json' => $result->toArray(),
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
            ]);
        } catch (Throwable $exception) {
            $parts = explode(':', $exception->getMessage(), 2);
            $intake->update([
                'status' => 'failed',
                'error_code' => Str::limit($parts[0] ?: 'extraction_failed', 80, ''),
                'error_message' => Str::limit(trim($parts[1] ?? $exception->getMessage()), 1000, ''),
            ]);

            report($exception);
            throw ValidationException::withMessages(['document' => trim($parts[1] ?? $exception->getMessage())]);
        }

        return $intake->refresh();
    }

    public function extractForAssistant(
        string $prompt,
        ?UploadedFile $upload,
        User $user,
        AiConversation $conversation,
    ): PropertyIntakeFile {
        abort_unless($conversation->user_id === $user->id, 403);

        $content = $upload?->getContent() ?? $prompt;
        $hash = hash('sha256', $content);
        $fingerprint = hash('sha256', implode('|', [
            $hash,
            mb_strtolower(trim($prompt)),
            (string) config('ai.provider'),
            (string) config('ai.extraction_model'),
        ]));
        $cached = PropertyIntakeFile::query()
            ->where('user_id', $user->id)
            ->where('request_fingerprint', $fingerprint)
            ->whereIn('status', ['ready', 'attached'])
            ->whereNotNull('extraction_json')
            ->latest()
            ->first();

        $token = (string) Str::uuid();
        $extension = $upload ? strtolower($upload->getClientOriginalExtension()) : 'txt';
        $path = 'property-intakes/'.$user->id.'/'.$token.'.'.$extension;
        $originalName = $upload
            ? Str::limit(basename($upload->getClientOriginalName()), 255, '')
            : 'vvr-ai-prompt-'.now()->format('Ymd-His').'.txt';
        $mimeType = $upload?->getMimeType() ?: 'text/plain';

        if (! Storage::disk('local')->put($path, $content)) {
            throw ValidationException::withMessages(['document' => 'The AI source could not be saved to private storage.']);
        }

        try {
            $intake = PropertyIntakeFile::query()->create([
                'token' => $token,
                'user_id' => $user->id,
                'ai_conversation_id' => $conversation->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size_bytes' => strlen($content),
                'sha256' => $hash,
                'request_fingerprint' => $fingerprint,
                'status' => $cached ? 'ready' : 'processing',
                'source_mode' => $upload ? 'prompt_and_document' : 'prompt',
                'user_prompt' => $prompt,
                'provider' => config('ai.provider'),
                'model' => config('ai.extraction_model'),
                'provider_response_id' => $cached?->provider_response_id,
                'extraction_json' => $cached?->extraction_json,
                'input_tokens' => $cached?->input_tokens,
                'output_tokens' => $cached?->output_tokens,
                'expires_at' => now()->addHours((int) config('ai.property_intake_expiration_hours', 24)),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($cached) {
            return $intake->refresh();
        }

        try {
            $result = $this->extractor->extract($intake);
            $intake->update([
                'status' => 'ready',
                'provider_response_id' => $result->responseId,
                'extraction_json' => $result->toArray(),
                'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
            ]);
        } catch (Throwable $exception) {
            $parts = explode(':', $exception->getMessage(), 2);
            $intake->update([
                'status' => 'failed',
                'error_code' => Str::limit($parts[0] ?: 'extraction_failed', 80, ''),
                'error_message' => Str::limit(trim($parts[1] ?? $exception->getMessage()), 1000, ''),
            ]);

            report($exception);
            throw ValidationException::withMessages(['prompt' => trim($parts[1] ?? $exception->getMessage())]);
        }

        return $intake->refresh();
    }

    public function review(PropertyIntakeFile $intake, User $user): array
    {
        abort_unless($intake->user_id === $user->id && $intake->status === 'ready', 403);

        $result = PropertyExtractionResult::fromArray(
            $intake->extraction_json ?? [],
            $intake->provider_response_id,
            $intake->input_tokens,
            $intake->output_tokens,
        );
        $values = $result->formValues($user->canViewPropertyFinancials());

        return [
            'values' => $values,
            'summary' => [
                'file_name' => $intake->original_name,
                'fields' => $result->fields,
                'missing_fields' => $result->missingFields,
                'warnings' => $result->warnings,
                'duplicates' => $this->duplicates($values, $intake),
            ],
        ];
    }

    private function duplicates(array $values, PropertyIntakeFile $intake): array
    {
        $matches = collect();

        if (filled($values['parcel_id'] ?? null) && filled($values['county'] ?? null) && filled($values['state'] ?? null)) {
            $matches = $matches->merge(Property::query()
                ->where('state', strtoupper($values['state']))
                ->where('normalized_county', $this->normalizer->county($values['county']))
                ->where('normalized_parcel_id', $this->normalizer->parcelId($values['parcel_id']))
                ->get()->map(fn (Property $property) => ['id' => $property->id, 'address' => $property->full_address, 'reason' => 'Parcel ID match']));
        }

        if (filled($values['address'] ?? null) && filled($values['city'] ?? null) && filled($values['state'] ?? null)) {
            $normalized = $this->normalizer->address($values['address'], $values['city'], $values['state'], $values['postal_code'] ?? null);
            $matches = $matches->merge(Property::query()->where('normalized_address', $normalized)->get()
                ->map(fn (Property $property) => ['id' => $property->id, 'address' => $property->full_address, 'reason' => 'Address match']));
        }

        $documentMatches = PropertyIntakeFile::query()
            ->where('sha256', $intake->sha256)
            ->where('status', 'attached')
            ->whereNotNull('property_id')
            ->whereKeyNot($intake->id)
            ->with('property:id,address,city,state,postal_code')
            ->get()
            ->map(fn (PropertyIntakeFile $file) => [
                'id' => $file->property_id,
                'address' => $file->property?->full_address ?? 'Property #'.$file->property_id,
                'reason' => 'This exact source document is already attached',
            ]);
        $matches = $matches->merge($documentMatches);

        return $matches->unique('id')->values()->all();
    }
}
