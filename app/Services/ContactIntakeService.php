<?php

namespace App\Services;

use App\Contracts\ContactDocumentExtractionInterface;
use App\Data\ContactExtractionResult;
use App\Domain\Contacts\ContactNormalizer;
use App\Models\Contact;
use App\Models\ContactIntakeFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactIntakeService
{
    public function __construct(
        private readonly ContactDocumentExtractionInterface $extractor,
        private readonly ContactNormalizer $normalizer,
    ) {}

    public function extract(UploadedFile $upload, User $user): ContactIntakeFile
    {
        $hash = hash_file('sha256', $upload->getRealPath());
        $existing = ContactIntakeFile::query()
            ->where('user_id', $user->id)
            ->where('sha256', $hash)
            ->whereIn('status', ['ready', 'attached'])
            ->latest()
            ->first();

        if ($existing?->status === 'attached') {
            throw ValidationException::withMessages([
                'document' => 'This exact file is already attached to contact #'.$existing->contact_id.'.',
            ]);
        }

        if ($existing && $existing->expires_at?->isFuture() && Storage::disk($existing->disk)->exists($existing->path)) {
            return $existing;
        }

        $token = (string) Str::uuid();
        $extension = strtolower($upload->getClientOriginalExtension());
        $path = 'contact-intakes/'.$user->id.'/'.$token.'.'.$extension;

        if (! Storage::disk('local')->put($path, $upload->getContent())) {
            throw ValidationException::withMessages(['document' => 'The contact document could not be saved to private storage.']);
        }

        try {
            $intake = ContactIntakeFile::query()->create([
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
                'expires_at' => now()->addHours((int) config('ai.contact_intake_expiration_hours', 24)),
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

    public function review(ContactIntakeFile $intake, User $user): array
    {
        abort_unless($intake->user_id === $user->id && $intake->status === 'ready', 403);

        $result = ContactExtractionResult::fromArray(
            $intake->extraction_json ?? [],
            $intake->provider_response_id,
            $intake->input_tokens,
            $intake->output_tokens,
        );
        $values = $result->formValues();

        return [
            'values' => $values,
            'summary' => [
                'file_name' => $intake->original_name,
                'fields' => $result->fields,
                'missing_fields' => $result->missingFields,
                'warnings' => $result->warnings,
                'duplicates' => $this->duplicates($values),
            ],
        ];
    }

    private function duplicates(array $values): array
    {
        $matches = collect();
        $email = $this->normalizer->email($values['email'] ?? null);
        $phone = $this->normalizer->phone($values['phone'] ?? null);

        if ($email) {
            $matches = $matches->merge(Contact::query()->where('normalized_email', $email)->get()
                ->map(fn (Contact $contact) => ['id' => $contact->id, 'name' => $contact->full_name, 'reason' => 'Email match']));
        }

        if ($phone) {
            $matches = $matches->merge(Contact::query()->where('normalized_phone', $phone)->get()
                ->map(fn (Contact $contact) => ['id' => $contact->id, 'name' => $contact->full_name, 'reason' => 'Phone match']));
        }

        if (filled($values['first_name'] ?? null) && filled($values['last_name'] ?? null) && filled($values['company'] ?? null)) {
            $matches = $matches->merge(Contact::query()
                ->where('first_name', $values['first_name'])
                ->where('last_name', $values['last_name'])
                ->where('company', $values['company'])
                ->get()->map(fn (Contact $contact) => ['id' => $contact->id, 'name' => $contact->full_name, 'reason' => 'Name and company match']));
        }

        return $matches->unique('id')->values()->all();
    }
}
