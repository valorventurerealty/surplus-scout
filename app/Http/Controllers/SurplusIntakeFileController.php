<?php

namespace App\Http\Controllers;

use App\Models\SurplusCase;
use App\Models\SurplusIntakeFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SurplusIntakeFileController extends Controller
{
    public function download(SurplusCase $surplus, SurplusIntakeFile $intakeFile): StreamedResponse
    {
        Gate::authorize('viewDocuments', $surplus);
        abort_unless($intakeFile->surplus_case_id === $surplus->id && $intakeFile->status === 'attached', 404);
        abort_unless(Storage::disk($intakeFile->disk)->exists($intakeFile->path), 404);

        return Storage::disk($intakeFile->disk)->download(
            $intakeFile->path,
            $intakeFile->original_name,
            ['Content-Type' => $intakeFile->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
