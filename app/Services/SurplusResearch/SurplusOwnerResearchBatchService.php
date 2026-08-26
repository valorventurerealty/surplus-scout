<?php

namespace App\Services\SurplusResearch;

use App\Enums\SurplusOwnerResearchStatus;
use App\Jobs\ResearchOsceolaSurplusOwnerJob;
use App\Models\SurplusCase;
use App\Models\SurplusOwnerResearchBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SurplusOwnerResearchBatchService
{
    /** @param list<int> $selectedIds */
    public function queue(string $mode, array $selectedIds, User $actor): SurplusOwnerResearchBatch
    {
        $batch = DB::transaction(function () use ($mode, $selectedIds, $actor): SurplusOwnerResearchBatch {
            $active = SurplusOwnerResearchBatch::query()->where('county', 'Osceola')
                ->whereIn('status', ['queued', 'running'])->lockForUpdate()->exists();
            if ($active) throw ValidationException::withMessages(['owner_research' => 'An Osceola owner-research batch is already active.']);

            $query = SurplusCase::query()->where('county', 'Osceola')->where('source_name', 'Osceola County Clerk')
                ->whereNotNull('normalized_parcel_id');
            if ($mode === 'selected') {
                // Selecting a completed case is the user's explicit rerun request.
                $query->whereKey($selectedIds)->whereNotIn('research_status', SurplusOwnerResearchStatus::activeValues());
            } else {
                $query->where('research_status', SurplusOwnerResearchStatus::Pending->value)->oldest('id');
                if ($mode === 'next_10') $query->limit(10);
            }
            $caseIds = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if ($caseIds === []) throw ValidationException::withMessages(['owner_research' => 'No eligible Osceola cases were found.']);
            if ($mode === 'selected' && count($caseIds) !== count(array_unique($selectedIds))) {
                throw ValidationException::withMessages(['case_ids' => 'One or more selected cases are not eligible for owner research.']);
            }

            return SurplusOwnerResearchBatch::query()->create([
                'token' => (string) Str::uuid(), 'county' => 'Osceola', 'mode' => $mode,
                'status' => 'queued', 'total_cases' => count($caseIds), 'case_ids' => $caseIds,
                'triggered_by' => $actor->id,
            ]);
        });

        foreach ($batch->case_ids as $caseId) {
            ResearchOsceolaSurplusOwnerJob::dispatch((int) $caseId, $batch->id, $actor->id)->afterCommit();
        }
        return $batch;
    }

    public function recordTerminalResult(SurplusOwnerResearchBatch $batch, string $researchStatus, bool $verified): void
    {
        DB::transaction(function () use ($batch, $researchStatus, $verified): void {
            $locked = SurplusOwnerResearchBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $updates = [
                'status' => 'running', 'started_at' => $locked->started_at ?? now(),
                'processed_cases' => $locked->processed_cases + 1,
                'verified_owners' => $locked->verified_owners + ($verified ? 1 : 0),
            ];
            $column = match ($researchStatus) {
                SurplusOwnerResearchStatus::ReadyForSkipTrace->value => 'ready_for_skip_trace',
                SurplusOwnerResearchStatus::BusinessResearchNeeded->value => 'business_research_needed',
                SurplusOwnerResearchStatus::EstateHeirResearchNeeded->value => 'estate_research_needed',
                SurplusOwnerResearchStatus::TrustResearchNeeded->value => 'trust_research_needed',
                SurplusOwnerResearchStatus::PropertyAppraiserError->value, SurplusOwnerResearchStatus::ParcelNotFound->value,
                    SurplusOwnerResearchStatus::TrimNoticeNotFound->value => 'errors',
                default => 'manual_review',
            };
            $updates[$column] = $locked->{$column} + 1;
            if ($updates['processed_cases'] >= $locked->total_cases) {
                $updates['status'] = ($updates['errors'] ?? $locked->errors) > 0 ? 'completed_with_warnings' : 'completed';
                $updates['completed_at'] = now();
            }
            $locked->update($updates);
        });
    }
}
