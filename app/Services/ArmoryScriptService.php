<?php

namespace App\Services;

use App\Models\ArmoryScript;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ArmoryScriptService
{
    public function create(array $data, ?UploadedFile $file, User $actor): ArmoryScript
    {
        $fileData = [];
        $storedPath = null;

        if ($file) {
            $hash = hash_file('sha256', $file->getRealPath());
            $duplicate = ArmoryScript::withTrashed()->where('sha256', $hash)->first();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'script_file' => "This file is already stored in Armory as \"{$duplicate->title}\".",
                ]);
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $directory = 'armory/'.Str::uuid();
            $storedPath = $file->storeAs($directory, 'script.'.$extension, 'local');

            if (! is_string($storedPath)) {
                throw ValidationException::withMessages([
                    'script_file' => 'The private script file could not be stored. Please try again.',
                ]);
            }

            $fileData = [
                'disk' => 'local',
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'sha256' => $hash,
                'uploaded_by' => $actor->id,
            ];

            if (blank($data['content_text'] ?? null) && in_array($extension, ['txt', 'md'], true)) {
                $fileData['content_text'] = Str::limit((string) file_get_contents($file->getRealPath()), 500000, '');
            }
        }

        try {
            return DB::transaction(fn (): ArmoryScript => ArmoryScript::query()->create([
                ...Arr::except($data, ['script_file']),
                ...$fileData,
                'token' => (string) Str::uuid(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function update(ArmoryScript $script, array $data, User $actor): ArmoryScript
    {
        return DB::transaction(function () use ($script, $data, $actor): ArmoryScript {
            $script->update([
                ...Arr::except($data, ['script_file']),
                'updated_by' => $actor->id,
            ]);

            return $script->refresh();
        });
    }
}
