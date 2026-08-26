<?php

namespace App\Console\Commands;

use App\Models\PropertyIntakeFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrunePropertyIntakeFiles extends Command
{
    protected $signature = 'properties:prune-intakes';

    protected $description = 'Delete expired, unattached property intake uploads from private storage';

    public function handle(): int
    {
        $deleted = 0;

        PropertyIntakeFile::query()
            ->whereNull('property_id')
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

        $this->info("Pruned {$deleted} expired property intake file(s).");

        return self::SUCCESS;
    }
}
