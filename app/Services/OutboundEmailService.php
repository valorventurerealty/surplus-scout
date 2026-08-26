<?php

namespace App\Services;

use App\Enums\OutboundEmailStatus;
use App\Jobs\SendOutboundEmailJob;
use App\Models\ArmoryEmailTemplate;
use App\Models\EmailSignature;
use App\Models\OutboundEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutboundEmailService
{
    public function __construct(private EmailAddressParser $addresses, private EmailContextResolver $context, private EmailMergeFieldService $merge, private SafeEmailHtmlRenderer $html) {}

    public function create(array $data, array $files, User $actor): OutboundEmail
    {
        return DB::transaction(function () use ($data, $files, $actor): OutboundEmail {
            $related = $this->context->resolve($data['related_type'] ?? null, $data['related_id'] ?? null, $actor);
            $email = OutboundEmail::query()->create($this->attributes($data, $actor, $related) + ['token' => (string) Str::uuid(), 'status' => OutboundEmailStatus::Draft]);
            $this->syncTemplateAttachments($email, $data['armory_email_template_id'] ?? null, $actor, count($files));
            $this->storeAttachments($email, $files, $actor);
            return $email;
        });
    }

    public function update(OutboundEmail $email, array $data, array $files, User $actor): OutboundEmail
    {
        return DB::transaction(function () use ($email, $data, $files, $actor): OutboundEmail {
            $related = $this->context->resolve($data['related_type'] ?? null, $data['related_id'] ?? null, $actor);
            $email->update($this->attributes($data, $actor, $related));
            $this->syncTemplateAttachments($email, $data['armory_email_template_id'] ?? null, $actor, count($files));
            $this->storeAttachments($email, $files, $actor);
            return $email->refresh();
        });
    }

    public function preview(OutboundEmail $email): array
    {
        $email->loadMissing(['user', 'related', 'primaryContact', 'signature', 'attachments']);
        $contact = $email->primaryContact ?: $this->context->contact($email->related);
        $values = $this->merge->values($email->related, $contact, $email->user);
        $subject = $this->merge->render($email->subject, $values);
        $body = $this->merge->render($email->body_text, $values);
        $signature = $email->signature ?: EmailSignature::query()->where('is_active', true)->orderByDesc('is_default')->first();
        $text = trim($body).($signature?->body_text ? "\n\n".$signature->body_text : '');
        $bodyHtml = $this->html->render($body);
        $signatureHtml = $signature?->body_html ?: ($signature?->body_text ? $this->html->render($signature->body_text) : '');
        $html = $bodyHtml.($signatureHtml ? '<hr style="margin:24px 0;border:0;border-top:1px solid #e2e8f0">'.$signatureHtml : '');
        $fingerprint = hash_hmac('sha256', json_encode(['to' => $email->to_json, 'cc' => $email->cc_json, 'bcc' => $email->bcc_json, 'subject' => $subject, 'text' => $text, 'html' => $html, 'attachments' => $email->attachments->pluck('sha256')->sort()->values()->all()], JSON_THROW_ON_ERROR), (string) config('app.key'));
        return ['subject' => $subject, 'body' => $body, 'text' => $text, 'html' => $html, 'signature' => $signature, 'unresolved' => $this->merge->unresolved($subject, $text), 'fingerprint' => $fingerprint];
    }

    public function queue(OutboundEmail $email, User $actor, string $reviewFingerprint): void
    {
        DB::transaction(function () use ($email, $actor, $reviewFingerprint): void {
            $email = OutboundEmail::query()->lockForUpdate()->findOrFail($email->id);
            if ($email->status !== OutboundEmailStatus::Draft) throw ValidationException::withMessages(['send' => 'Only a draft can be sent.']);
            $this->ensureWithinLimit($actor);
            $related = $email->related;
            if ($related) $this->context->resolve($this->context->type($related), $related->getKey(), $actor);
            if ($email->primary_contact_id) $this->context->resolve('contact', $email->primary_contact_id, $actor);
            $preview = $this->preview($email);
            if (! hash_equals($preview['fingerprint'], $reviewFingerprint)) throw ValidationException::withMessages(['send' => 'The message changed after it was reviewed. Refresh this page and review it again.']);
            $unresolved = $preview['unresolved'];
            if ($unresolved) throw ValidationException::withMessages(['send' => 'Resolve these merge fields before sending: '.implode(', ', $unresolved)]);
            $signature = $preview['signature'];
            $signatureText = $signature?->body_text;
            $signatureHtml = $signature?->body_html;
            $email->update(['status' => OutboundEmailStatus::Queued, 'subject' => $preview['subject'], 'final_text' => $preview['text'], 'final_html' => $preview['html'], 'signature_text' => $signatureText, 'signature_html' => $signatureHtml, 'queued_at' => now(), 'failure_message' => null]);
            SendOutboundEmailJob::dispatch($email->id)->afterCommit();
        });
    }

    public function retry(OutboundEmail $email, User $actor): void
    {
        DB::transaction(function () use ($email, $actor): void {
            $email = OutboundEmail::query()->lockForUpdate()->findOrFail($email->id);
            if ($email->status !== OutboundEmailStatus::Failed) throw ValidationException::withMessages(['send' => 'Only a failed email can be retried.']);
            $this->ensureWithinLimit($actor);
            $email->update(['status' => OutboundEmailStatus::Queued, 'queued_at' => now(), 'failure_message' => null, 'failed_at' => null]);
            SendOutboundEmailJob::dispatch($email->id)->afterCommit();
        });
    }

    public function cancel(OutboundEmail $email): void
    {
        DB::transaction(function () use ($email): void {
            $email = OutboundEmail::query()->lockForUpdate()->findOrFail($email->id);
            if ($email->status !== OutboundEmailStatus::Queued) throw ValidationException::withMessages(['send' => 'This email has already started delivery and cannot be cancelled.']);
            $email->update(['status' => OutboundEmailStatus::Cancelled, 'cancelled_at' => now()]);
        });
    }

    public function deleteDraft(OutboundEmail $email): void
    {
        DB::transaction(function () use ($email): void {
            $email = OutboundEmail::query()->lockForUpdate()->findOrFail($email->id);
            if ($email->status !== OutboundEmailStatus::Draft) throw ValidationException::withMessages(['delete' => 'Only an unsent draft can be deleted.']);
            $email->delete();
        });
    }

    private function ensureWithinLimit(User $actor): void
    {
        $count = OutboundEmail::query()->where('user_id', $actor->id)->whereIn('status', [OutboundEmailStatus::Queued->value, OutboundEmailStatus::Sending->value, OutboundEmailStatus::Sent->value])->where('queued_at', '>=', now()->subHour())->count();
        if ($count >= config('email.hourly_user_limit')) throw ValidationException::withMessages(['send' => 'Hourly email limit reached. Try again later.']);
    }

    private function attributes(array $data, User $actor, ?Model $related): array
    {
        $primaryContact = isset($data['primary_contact_id']) ? $this->context->resolve('contact', $data['primary_contact_id'], $actor) : null;
        $fromAddress = (string) config('mail.from.address');
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) throw ValidationException::withMessages(['to' => 'The system sender address is not configured correctly.']);
        $to = $this->addresses->parse($data['to'] ?? '', 'to', true);
        $cc = $this->addresses->parse($data['cc'] ?? '', 'cc');
        $bcc = $this->addresses->parse($data['bcc'] ?? '', 'bcc');
        if (count(array_unique([...$to, ...$cc, ...$bcc])) > config('email.max_recipients')) throw ValidationException::withMessages(['to' => 'The total recipient limit is '.config('email.max_recipients').'.']);
        return ['user_id' => $actor->id, 'primary_contact_id' => $primaryContact?->getKey(), 'armory_email_template_id' => $data['armory_email_template_id'] ?? null, 'email_signature_id' => $data['email_signature_id'] ?? null, 'related_type' => $related?->getMorphClass(), 'related_id' => $related?->getKey(), 'from_address' => $fromAddress, 'from_name' => (string) config('mail.from.name'), 'to_json' => $to, 'cc_json' => $cc, 'bcc_json' => $bcc, 'subject' => trim($data['subject']), 'body_text' => trim($data['body_text'])];
    }

    private function storeAttachments(OutboundEmail $email, array $files, User $actor): void
    {
        if ($email->attachments()->count() + count($files) > config('email.max_attachments')) throw ValidationException::withMessages(['attachments' => 'This draft already has attachments. The total limit is '.config('email.max_attachments').'.']);
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) continue;
            $hash = hash_file('sha256', $file->getRealPath());
            if ($email->attachments()->where('sha256', $hash)->exists()) continue;
            $path = $file->store("outbound-email/{$email->token}", 'local');
            if (! $path) throw ValidationException::withMessages(['attachments' => 'An attachment could not be stored.']);
            $email->attachments()->create(['token' => (string) Str::uuid(), 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => $hash, 'uploaded_by' => $actor->id]);
        }
    }

    private function syncTemplateAttachments(OutboundEmail $email, mixed $templateId, User $actor, int $pendingManualFiles): void
    {
        $template = $templateId ? ArmoryEmailTemplate::query()->with('attachments')->findOrFail($templateId) : null;
        $sources = $template?->attachments ?? collect();
        $manualCount = $email->attachments()->whereNull('armory_email_template_attachment_id')->count();
        if ($manualCount + $sources->count() + $pendingManualFiles > config('email.max_attachments')) {
            throw ValidationException::withMessages(['attachments' => 'The selected template files plus this draft’s attachments exceed the total limit of '.config('email.max_attachments').'.']);
        }

        foreach ($sources as $source) {
            if (! Storage::disk($source->disk)->exists($source->path)) {
                throw ValidationException::withMessages(['attachments' => "The template attachment {$source->original_name} is unavailable. Ask an Armory manager to replace it."]);
            }
        }

        $sourceIds = $sources->pluck('id')->all();
        $email->attachments()->whereNotNull('armory_email_template_attachment_id')
            ->when($sourceIds, fn ($query) => $query->whereNotIn('armory_email_template_attachment_id', $sourceIds))
            ->get()->each(function ($attachment): void {
                $disk = Storage::disk($attachment->disk);
                if ($disk->exists($attachment->path) && ! $disk->delete($attachment->path)) {
                    throw ValidationException::withMessages(['attachments' => "Could not replace the template attachment {$attachment->original_name}."]);
                }
                $attachment->delete();
            });

        foreach ($sources as $source) {
            if ($email->attachments()->where('armory_email_template_attachment_id', $source->id)->exists()) continue;
            $extension = pathinfo($source->original_name, PATHINFO_EXTENSION);
            $target = "outbound-email/{$email->token}/".(string) Str::uuid().($extension ? '.'.strtolower($extension) : '');
            $disk = Storage::disk($source->disk);
            if (! $disk->copy($source->path, $target)) {
                throw ValidationException::withMessages(['attachments' => "Could not add the template attachment {$source->original_name} to this draft."]);
            }
            $email->attachments()->create(['token' => (string) Str::uuid(), 'armory_email_template_attachment_id' => $source->id, 'disk' => $source->disk, 'path' => $target, 'original_name' => $source->original_name, 'mime_type' => $source->mime_type, 'size_bytes' => $source->size_bytes, 'sha256' => $source->sha256, 'uploaded_by' => $actor->id]);
        }
    }

}
