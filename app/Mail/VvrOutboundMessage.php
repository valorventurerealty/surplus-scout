<?php

namespace App\Mail;

use App\Models\OutboundEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VvrOutboundMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OutboundEmail $outboundEmail) {}

    public function build(): static
    {
        $message = $this->from($this->outboundEmail->from_address, $this->outboundEmail->from_name)
            ->subject($this->outboundEmail->subject)
            ->view('emails.outbound-html', ['email' => $this->outboundEmail])
            ->text('emails.outbound-text', ['email' => $this->outboundEmail]);
        foreach ($this->outboundEmail->attachments as $attachment) {
            $message->attachFromStorageDisk($attachment->disk, $attachment->path, $attachment->original_name, ['mime' => $attachment->mime_type]);
        }
        return $message;
    }
}
