<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactIntakeFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactIntakeFileController extends Controller
{
    public function download(Contact $contact, ContactIntakeFile $intakeFile): StreamedResponse
    {
        Gate::authorize('viewSourceDocuments', $contact);
        abort_unless($intakeFile->contact_id === $contact->id && $intakeFile->status === 'attached', 404);
        abort_unless(Storage::disk($intakeFile->disk)->exists($intakeFile->path), 404);

        return Storage::disk($intakeFile->disk)->download(
            $intakeFile->path,
            $intakeFile->original_name,
            ['Content-Type' => $intakeFile->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
