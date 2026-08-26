<?php

namespace App\Console\Commands;

use App\Services\SurplusContactMergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateSurplusContacts extends Command
{
    protected $signature = 'contacts:merge-surplus-duplicates {--execute : Transfer associations and soft-archive duplicate contacts}';

    protected $description = 'Preview or merge exact-name duplicate Surplus contacts';

    public function handle(SurplusContactMergeService $service): int
    {
        $groups = $service->duplicateGroups();
        if ($groups->isEmpty()) {
            $this->info('No active exact-name duplicate Surplus contacts were found.');
            return self::SUCCESS;
        }

        $previews = $groups->map(fn (array $group): array => $service->preview($group));
        $this->table(['Name', 'Keep contact', 'Archive contacts', 'Cases moved', 'Tasks moved'], $previews->map(fn (array $preview): array => [
            $preview['name'], '#'.$preview['canonical_id'], implode(', ', array_map(fn ($id): string => '#'.$id, $preview['duplicate_ids'])),
            $preview['surplus_cases'], $preview['tasks'],
        ])->all());

        if (! $this->option('execute')) {
            $this->warn('Preview only. No records were changed. Run again with --execute to perform this exact-name merge.');
            return self::SUCCESS;
        }

        $results = DB::transaction(fn () => $groups->map(fn (array $group): array => $service->mergeGroup($group)));
        $merged = $results->sum(fn (array $result): int => count($result['merged_ids']));
        $this->info("Merged and soft-archived {$merged} duplicate Surplus contact(s) across {$groups->count()} name group(s).");

        return self::SUCCESS;
    }
}
