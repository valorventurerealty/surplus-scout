<?php

namespace App\Services;

use App\Models\Sop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SopService
{
    public function create(array $data, ?UploadedFile $file, User $actor): Sop
    {
        [$fileData, $storedPath] = $this->storeFile($file, null, $actor, blank($data['content_text'] ?? null));

        try {
            return DB::transaction(fn (): Sop => Sop::query()->create([
                ...Arr::except($data, ['sop_file', 'remove_file']), ...$fileData,
                'token' => (string) Str::uuid(), 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]));
        } catch (Throwable $exception) {
            if ($storedPath) { Storage::disk('local')->delete($storedPath); }
            throw $exception;
        }
    }

    public function update(Sop $sop, array $data, ?UploadedFile $file, User $actor): Sop
    {
        [$fileData, $storedPath] = $this->storeFile($file, $sop, $actor, blank($data['content_text'] ?? null));
        $oldDisk = $sop->disk;
        $oldPath = $sop->path;
        $remove = (bool) ($data['remove_file'] ?? false);
        if ($remove && ! $file) {
            $fileData = ['disk' => null, 'path' => null, 'original_name' => null, 'mime_type' => null, 'size_bytes' => null, 'sha256' => null, 'uploaded_by' => null];
        }

        try {
            $updated = DB::transaction(function () use ($sop, $data, $fileData, $actor): Sop {
                $sop->update([...Arr::except($data, ['sop_file', 'remove_file']), ...$fileData, 'updated_by' => $actor->id]);
                return $sop->refresh();
            });
        } catch (Throwable $exception) {
            if ($storedPath) { Storage::disk('local')->delete($storedPath); }
            throw $exception;
        }

        if (($file || $remove) && $oldDisk && $oldPath && ($oldPath !== $updated->path)) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        return $updated;
    }

    /** @return array{0: array<string, mixed>, 1: string|null} */
    private function storeFile(?UploadedFile $file, ?Sop $current, User $actor, bool $importText): array
    {
        if (! $file) { return [[], null]; }

        $hash = hash_file('sha256', $file->getRealPath());
        $duplicate = Sop::withTrashed()->where('sha256', $hash)->when($current, fn ($query) => $query->whereKeyNot($current->id))->first();
        if ($duplicate) {
            throw ValidationException::withMessages(['sop_file' => "This file is already stored as SOP \"{$duplicate->title}\"."]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = $file->storeAs('sops/'.Str::uuid(), 'procedure.'.$extension, 'local');
        if (! is_string($storedPath)) {
            throw ValidationException::withMessages(['sop_file' => 'The private SOP file could not be stored. Please try again.']);
        }

        $data = [
            'disk' => 'local', 'path' => $storedPath, 'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(),
            'sha256' => $hash, 'uploaded_by' => $actor->id,
        ];
        if ($importText && in_array($extension, ['txt', 'md'], true)) {
            $data['content_text'] = Str::limit((string) file_get_contents($file->getRealPath()), 500000, '');
        }

        return [$data, $storedPath];
    }
}
