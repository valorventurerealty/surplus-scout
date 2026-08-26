<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyIntakeFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyIntakeFileController extends Controller
{
    public function download(Property $property, PropertyIntakeFile $intakeFile): StreamedResponse
    {
        Gate::authorize('viewSourceDocuments', $property);
        abort_unless($intakeFile->property_id === $property->id && $intakeFile->status === 'attached', 404);
        abort_unless(Storage::disk($intakeFile->disk)->exists($intakeFile->path), 404);

        return Storage::disk($intakeFile->disk)->download(
            $intakeFile->path,
            $intakeFile->original_name,
            ['Content-Type' => $intakeFile->mime_type, 'X-Content-Type-Options' => 'nosniff'],
        );
    }
}
