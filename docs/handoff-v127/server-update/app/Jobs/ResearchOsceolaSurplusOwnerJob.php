<?php

namespace App\Jobs;

use App\Enums\SurplusOwnerResearchStatus;
use App\Models\SurplusCase;
use App\Models\SurplusOwnerResearchBatch;
use App\Models\User;
use App\Services\SurplusResearch\SurplusOwnerResearchBatchService;
use App\Services\SurplusResearch\SurplusOwnerResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResearchOsceolaSurplusOwnerJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 85;
    public int $uniqueFor = 3600;

    public function __construct(public readonly int $caseId, public readonly int $batchId, public readonly int $actorId)
    {
        $this->onQueue('surplus-research');
    }
    public function uniqueId(): string { return $this->batchId.':'.$this->caseId; }
    public function backoff(): array { return [60, 300, 900]; }

    public function handle(SurplusOwnerResearchService $service): void
    {
        $service->research(
            SurplusCase::query()->findOrFail($this->caseId),
            SurplusOwnerResearchBatch::query()->findOrFail($this->batchId),
            User::query()->findOrFail($this->actorId),
        );
    }

    public function failed(?\Throwable $error): void
    {
        $case = SurplusCase::query()->find($this->caseId);
        $batch = SurplusOwnerResearchBatch::query()->find($this->batchId);
        if ($case) $case->update(['research_status' => SurplusOwnerResearchStatus::PropertyAppraiserError->value]);
        if ($batch) app(SurplusOwnerResearchBatchService::class)->recordTerminalResult($batch, SurplusOwnerResearchStatus::PropertyAppraiserError->value, false);
        Log::error('Osceola owner research exhausted retries.', ['case_id' => $this->caseId, 'batch_id' => $this->batchId, 'message' => $error?->getMessage()]);
    }
}
