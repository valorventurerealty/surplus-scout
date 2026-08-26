<?php

namespace App\Http\Controllers;

use App\Models\ArmoryEmailTemplate;
use App\Models\ArmoryEmailTemplateAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArmoryEmailTemplateAttachmentController extends Controller
{
    public function __invoke(ArmoryEmailTemplate $emailTemplate, ArmoryEmailTemplateAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $emailTemplate);
        abort_unless($attachment->armory_email_template_id === $emailTemplate->id, 404);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name);
    }
}
