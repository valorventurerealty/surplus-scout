<?php

namespace App\Jobs;

use App\Enums\OutboundEmailStatus;
use App\Mail\VvrOutboundMessage;
use App\Models\OutboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendOutboundEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;
    public int $uniqueFor = 3600;

    public function __construct(public int $outboundEmailId) {}
    public function uniqueId(): string { return (string) $this->outboundEmailId; }

    public function handle(): void
    {
        $email = DB::transaction(function (): ?OutboundEmail {
            $email = OutboundEmail::query()->lockForUpdate()->with(['attachments', 'user'])->find($this->outboundEmailId);
            if (! $email || $email->status !== OutboundEmailStatus::Queued) return null;
            if (! $email->user->canSendEmail() || ! $email->user->can('view', $email)) {
                $email->update(['status' => OutboundEmailStatus::Failed, 'failed_at' => now(), 'failure_message' => 'Sender no longer has permission to send this email or access its linked CRM record.']);
                return null;
            }
            $email->update(['status' => OutboundEmailStatus::Sending, 'sending_at' => now(), 'last_attempt_at' => now(), 'attempt_count' => $email->attempt_count + 1]);
            return $email;
        });
        if (! $email) return;

        try {
            Mail::to($email->to_json)->cc($email->cc_json ?? [])->bcc($email->bcc_json ?? [])->send(new VvrOutboundMessage($email));
            $email->update(['status' => OutboundEmailStatus::Sent, 'sent_at' => now(), 'failure_message' => null]);
        } catch (Throwable $exception) {
            $email->update(['status' => OutboundEmailStatus::Failed, 'failed_at' => now(), 'failure_message' => Str::limit($exception->getMessage(), 2000)]);
            throw $exception;
        }
    }
}
