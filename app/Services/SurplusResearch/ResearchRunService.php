<?php

namespace App\Services\SurplusResearch;

use App\Contracts\SurplusResearch\CountySurplusSourceInterface;
use App\Enums\SurplusResearchRunStatus;
use App\Jobs\RunOsceolaSurplusResearchJob;
use App\Models\SurplusResearchRun;
use App\Models\User;
use App\Services\SurplusResearch\Osceola\OsceolaPdfParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearchRunService
{
    public function __construct(
        private readonly CountySurplusSourceInterface $source,
        private readonly OsceolaPdfParser $parser,
        private readonly SurplusImportService $importer,
    ) {}

    public function queue(User $actor): SurplusResearchRun
    {
        $run = DB::transaction(function () use ($actor): SurplusResearchRun {
            $active = SurplusResearchRun::query()->where('county', 'Osceola')
                ->whereIn('status', [SurplusResearchRunStatus::Queued->value, SurplusResearchRunStatus::Running->value])
                ->lockForUpdate()->exists();
            if ($active) {
                throw ValidationException::withMessages(['research' => 'An Osceola research run is already active.']);
            }
            return SurplusResearchRun::query()->create([
                'token' => (string) Str::uuid(), 'county' => 'Osceola',
                'source_name' => 'Osceola County Clerk',
                'source_url' => (string) config('surplus_research.osceola.source_url'),
                'status' => SurplusResearchRunStatus::Queued, 'triggered_by' => $actor->id,
            ]);
        });

        RunOsceolaSurplusResearchJob::dispatch($run->id)->afterCommit();
        return $run;
    }

    public function execute(SurplusResearchRun $run): void
    {
        if (! in_array($run->status, [SurplusResearchRunStatus::Queued, SurplusResearchRunStatus::Running], true)) return;
        $run->update(['status' => SurplusResearchRunStatus::Running, 'started_at' => $run->started_at ?? now(), 'error_message' => null]);

        $download = $this->source->download();
        $path = 'surplus-research/osceola/'.$run->token.'.pdf';
        if (! Storage::disk('local')->put($path, $download->contents)) {
            throw new \RuntimeException('The Clerk report could not be stored privately.');
        }

        $run->update([
            'source_url' => $download->sourceUrl, 'source_file_disk' => 'local',
            'source_file_path' => $path, 'source_file_hash' => $download->sha256,
            'source_file_size' => $download->size,
        ]);

        // Parsing and structural validation finish before any case is changed.
        $report = $this->parser->parse($download->contents);
        $actor = User::query()->findOrFail($run->triggered_by);
        $counts = $this->importer->import($report, $run, $actor);
        $warningCount = count($report->warnings);

        $run->update([
            'status' => $warningCount > 0 ? SurplusResearchRunStatus::CompletedWithWarnings : SurplusResearchRunStatus::Completed,
            'source_report_date' => $report->reportDate, 'completed_at' => now(),
            'records_found' => count($report->records), 'failed_records' => $report->failedRows,
            'warning_count' => $warningCount, 'warnings' => $report->warnings,
            ...$counts,
        ]);
    }

    public function markFailed(SurplusResearchRun $run, \Throwable $exception): void
    {
        $run->update([
            'status' => SurplusResearchRunStatus::Failed, 'completed_at' => now(),
            'error_message' => Str::limit($exception->getMessage(), 2000),
        ]);
    }
}
