<?php

namespace App\Services;

use App\Models\ArmoryEmailTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ArmoryEmailTemplateService
{
    public function create(array $data, array $files, User $actor): ArmoryEmailTemplate
    {
        return DB::transaction(function () use ($data, $files, $actor): ArmoryEmailTemplate {
            $template = ArmoryEmailTemplate::query()->create([
                ...$this->attributes($data),
                'token' => (string) Str::uuid(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->storeAttachments($template, $files, $actor);

            return $template->refresh();
        });
    }

    public function update(ArmoryEmailTemplate $template, array $data, array $files, User $actor): ArmoryEmailTemplate
    {
        return DB::transaction(function () use ($template, $data, $files, $actor): ArmoryEmailTemplate {
            $this->removeAttachments($template, $data['remove_attachments'] ?? []);
            $template->update([...$this->attributes($data), 'updated_by' => $actor->id]);
            $this->storeAttachments($template, $files, $actor);

            return $template->refresh();
        });
    }

    private function attributes(array $data): array
    {
        unset($data['attachments'], $data['remove_attachments']);

        return $data;
    }

    private function storeAttachments(ArmoryEmailTemplate $template, array $files, User $actor): void
    {
        if ($template->attachments()->count() + count($files) > config('email.max_attachments')) {
            throw ValidationException::withMessages(['attachments' => 'The total template attachment limit is '.config('email.max_attachments').'.']);
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) continue;
            $hash = hash_file('sha256', $file->getRealPath());
            if ($template->attachments()->where('sha256', $hash)->exists()) continue;
            $path = $file->store("armory-email-template/{$template->token}", 'local');
            if (! $path) throw ValidationException::withMessages(['attachments' => 'A template attachment could not be stored.']);
            $template->attachments()->create(['token' => (string) Str::uuid(), 'disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => $hash, 'uploaded_by' => $actor->id]);
        }
    }

    private function removeAttachments(ArmoryEmailTemplate $template, array $ids): void
    {
        $template->attachments()->whereKey($ids)->get()->each(function ($attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });
    }
}
