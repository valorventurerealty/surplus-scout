<?php

namespace App\Http\Controllers;

use App\Models\OutboundEmail;
use App\Models\OutboundEmailAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OutboundEmailAttachmentController extends Controller
{
    public function __invoke(OutboundEmail $outboundEmail, OutboundEmailAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $outboundEmail);
        abort_unless($attachment->outbound_email_id === $outboundEmail->id && Storage::disk($attachment->disk)->exists($attachment->path), 404);
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
