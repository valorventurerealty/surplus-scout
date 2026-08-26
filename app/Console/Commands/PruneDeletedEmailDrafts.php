<?php

namespace App\Console\Commands;

use App\Models\OutboundEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDeletedEmailDrafts extends Command
{
    protected $signature = 'email:prune-deleted-drafts';
    protected $description = 'Permanently purge expired deleted email drafts and their private attachments';

    public function handle(): int
    {
        $deleted = 0;
        $cutoff = now()->subDays(max(1, (int) config('email.deleted_draft_retention_days')));

        OutboundEmail::onlyTrashed()->with('attachments')->where('deleted_at', '<=', $cutoff)
            ->chunkById(100, function ($emails) use (&$deleted): void {
                foreach ($emails as $email) {
                    $storageFailed = false;
                    foreach ($email->attachments as $attachment) {
                        $disk = Storage::disk($attachment->disk);
                        if ($disk->exists($attachment->path) && ! $disk->delete($attachment->path)) {
                            $this->error('Could not delete '.$attachment->path);
                            $storageFailed = true;
                        }
                    }
                    if ($storageFailed) continue;
                    $email->forceDelete();
                    $deleted++;
                }
            });

        $this->info("Purged {$deleted} deleted email draft(s).");
        return self::SUCCESS;
    }
}
