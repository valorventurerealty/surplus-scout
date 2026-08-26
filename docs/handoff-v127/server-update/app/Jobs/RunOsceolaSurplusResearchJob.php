<?php

namespace App\Jobs;

use App\Models\SurplusResearchRun;
use App\Services\SurplusResearch\ResearchRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunOsceolaSurplusResearchJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;
    public int $uniqueFor = 600;

    public function __construct(public readonly int $runId)
    {
        $this->onQueue('surplus-research');
    }
    public function uniqueId(): string { return 'osceola'; }

    public function handle(ResearchRunService $service): void
    {
        $run = SurplusResearchRun::query()->findOrFail($this->runId);
        try {
            $service->execute($run);
        } catch (\Throwable $exception) {
            $service->markFailed($run->fresh(), $exception);
            Log::error('Osceola surplus research run failed.', [
                'run_id' => $run->id, 'exception' => $exception::class, 'message' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $run = SurplusResearchRun::query()->find($this->runId);
        if ($run && $run->status->active()) {
            $run->update([
                'status' => \App\Enums\SurplusResearchRunStatus::Failed,
                'completed_at' => now(),
                'error_message' => \Illuminate\Support\Str::limit($exception?->getMessage() ?? 'The research worker stopped unexpectedly.', 2000),
            ]);
        }
    }
}
