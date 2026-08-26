<?php

namespace App\Console\Commands;

use App\Models\ArmorySession;
use Illuminate\Console\Command;

class PruneDeletedArmorySessions extends Command
{
    protected $signature = 'armory:prune-deleted-sessions';

    protected $description = 'Permanently purge guided sessions after the configured recovery period';

    public function handle(): int
    {
        $deleted = 0;
        $cutoff = now()->subDays(max(1, (int) config('armory.deleted_session_retention_days')));

        ArmorySession::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->chunkById(100, function ($sessions) use (&$deleted): void {
                foreach ($sessions as $session) {
                    $session->forceDelete();
                    $deleted++;
                }
            });

        $this->info("Purged {$deleted} deleted guided session(s).");

        return self::SUCCESS;
    }
}
