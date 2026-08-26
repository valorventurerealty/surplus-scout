<?php

namespace App\Console\Commands;

use App\Models\ContactIntakeFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneContactIntakeFiles extends Command
{
    protected $signature = 'contacts:prune-intakes';

    protected $description = 'Delete expired, unattached contact intake uploads from private storage';

    public function handle(): int
    {
        $deleted = 0;

        ContactIntakeFile::query()
            ->whereNull('contact_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($files) use (&$deleted): void {
                foreach ($files as $file) {
                    $disk = Storage::disk($file->disk);
                    if ($disk->exists($file->path) && ! $disk->delete($file->path)) {
                        $this->error('Could not delete '.$file->path);
                        continue;
                    }

                    $file->delete();
                    $deleted++;
                }
            });

        $this->info("Pruned {$deleted} expired contact intake file(s).");

        return self::SUCCESS;
    }
}
