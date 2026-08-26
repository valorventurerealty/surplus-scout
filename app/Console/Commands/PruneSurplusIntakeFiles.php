<?php

namespace App\Console\Commands;

use App\Models\SurplusIntakeFile;
use App\Models\AiSurplusCsvImport;
use App\Models\AiPreAuctionCsvImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneSurplusIntakeFiles extends Command
{
    protected $signature = 'surplus:prune-intakes';

    protected $description = 'Delete expired, unattached Surplus document and CSV intake uploads from private storage';

    public function handle(): int
    {
        $deleted = 0;
        SurplusIntakeFile::query()->whereNull('surplus_case_id')->whereNotNull('expires_at')->where('expires_at', '<=', now())
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
        AiSurplusCsvImport::query()->where('status', 'ready')->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->chunkById(100, function ($imports) use (&$deleted): void {
                foreach ($imports as $import) {
                    $disk = Storage::disk($import->disk);
                    if ($disk->exists($import->path) && ! $disk->delete($import->path)) {
                        $this->error('Could not delete '.$import->path);
                        continue;
                    }
                    $import->delete();
                    $deleted++;
                }
            });
        AiPreAuctionCsvImport::query()->where('status', 'ready')->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->chunkById(100, function ($imports) use (&$deleted): void {
                foreach ($imports as $import) {
                    $disk = Storage::disk($import->disk);
                    if ($disk->exists($import->path) && ! $disk->delete($import->path)) {
                        $this->error('Could not delete '.$import->path);
                        continue;
                    }
                    $import->delete();
                    $deleted++;
                }
            });
        $this->info("Pruned {$deleted} expired Surplus intake file(s).");
        return self::SUCCESS;
    }
}
