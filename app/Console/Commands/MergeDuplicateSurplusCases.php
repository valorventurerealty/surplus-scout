<?php

namespace App\Console\Commands;

use App\Services\SurplusCaseMergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateSurplusCases extends Command
{
    protected $signature = 'surplus:merge-duplicate-cases {--execute : Transfer associations and soft-archive duplicate cases}';
    protected $description = 'Preview or merge duplicate Surplus cases with the same state, normalized parcel, and claimant';

    public function handle(SurplusCaseMergeService $service): int
    {
        $groups = $service->duplicateGroups();
        if ($groups->isEmpty()) {
            $this->info('No duplicate Surplus cases were found.');
            return self::SUCCESS;
        }
        $previews = $groups->map(fn (object $group): array => $service->preview($group));
        $this->table(['Parcel', 'Claimant contact', 'Keep case', 'Archive cases'], $previews->map(fn (array $preview): array => [
            $preview['parcel'], '#'.$preview['claimant_contact_id'], $preview['keep'], implode(', ', $preview['archive']),
        ])->all());
        if (! $this->option('execute')) {
            $this->warn('Preview only. No records were changed. Run again with --execute to merge these duplicate cases.');
            return self::SUCCESS;
        }
        $results = DB::transaction(fn () => $groups->map(fn (object $group): array => $service->mergeGroup($group)));
        $count = $results->sum(fn (array $result): int => count($result['archived']));
        $this->info("Merged and soft-archived {$count} duplicate Surplus case(s).");

        return self::SUCCESS;
    }
}
