<?php

namespace App\Services\SurplusResearch;

use App\Data\SurplusResearch\CountySurplusReportData;
use App\Enums\SurplusCaseStatus;
use App\Models\SurplusAmountHistory;
use App\Models\SurplusCase;
use App\Models\SurplusResearchRun;
use App\Models\User;
use App\Services\SurplusCaseService;
use Illuminate\Support\Facades\DB;

class SurplusImportService
{
    public function __construct(
        private readonly SurplusDuplicateService $duplicates,
        private readonly SurplusCaseService $cases,
    ) {}

    /** @return array{new_records:int,existing_records:int,amount_changes:int,removed_records:int} */
    public function import(CountySurplusReportData $report, SurplusResearchRun $run, User $actor): array
    {
        return DB::transaction(function () use ($report, $run, $actor): array {
            $counts = ['new_records' => 0, 'existing_records' => 0, 'amount_changes' => 0, 'removed_records' => 0];
            $seen = [];

            foreach ($report->records as $record) {
                $seen[] = $record->uniqueKey;
                $case = $this->duplicates->find($record);
                if ($case === null) {
                    $this->cases->create([
                        'status' => SurplusCaseStatus::Research->value,
                        'research_status' => $record->warnings === [] ? 'pending_owner_research' : 'manual_review',
                        'surplus_availability' => 'available',
                        'source' => 'Osceola Clerk', 'source_name' => 'Osceola County Clerk',
                        'source_url' => $run->source_url, 'source_report_date' => $report->reportDate,
                        'source_last_seen_at' => now(), 'last_surplus_research_run_id' => $run->id,
                        'state' => $record->state, 'county' => $record->county,
                        'parcel_id' => $record->parcelIdRaw, 'parcel_id_raw' => $record->parcelIdRaw,
                        'normalized_parcel_id' => $record->parcelIdNormalized, 'clerk_unique_key' => $record->uniqueKey,
                        'tax_deed_number' => $record->taxDeedNumber, 'certificate_number' => $record->certificateNumber,
                        'sale_date' => $record->saleDate, 'surplus_amount' => $record->surplusAmount,
                        'notes' => $record->warnings === [] ? null : 'Clerk import review: '.implode(' ', $record->warnings),
                    ], $actor);
                    $counts['new_records']++;
                    continue;
                }

                $case = SurplusCase::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
                $oldAmount = $case->surplus_amount;
                $amountChanged = $oldAmount === null || $this->toCents((string) $oldAmount) !== $this->toCents($record->surplusAmount);
                if ($amountChanged) {
                    SurplusAmountHistory::query()->firstOrCreate(
                        ['surplus_case_id' => $case->id, 'research_run_id' => $run->id],
                        ['previous_amount' => $oldAmount, 'new_amount' => $record->surplusAmount, 'changed_at' => now()],
                    );
                    $counts['amount_changes']++;
                } else {
                    $counts['existing_records']++;
                }

                $updates = [
                    'parcel_id_raw' => $record->parcelIdRaw, 'clerk_unique_key' => $record->uniqueKey,
                    'surplus_amount' => $record->surplusAmount, 'surplus_availability' => 'available',
                    'source' => 'Osceola Clerk', 'source_name' => 'Osceola County Clerk',
                    'source_url' => $run->source_url, 'source_report_date' => $report->reportDate,
                    'source_last_seen_at' => now(), 'last_surplus_research_run_id' => $run->id,
                    'updated_by' => $actor->id,
                ];
                if ($record->warnings !== [] && in_array($case->research_status, [null, 'pending_owner_research'], true)) {
                    $updates['research_status'] = 'manual_review';
                    $updates['notes'] = trim(($case->notes ? $case->notes."\n\n" : '').'Clerk import review: '.implode(' ', $record->warnings));
                }
                $this->cases->update($case, $updates, $actor);
            }

            // An unidentifiable row could be a case that merely failed parsing. Never
            // infer removals unless every candidate row was safely accounted for.
            $removed = $report->failedRows === 0
                ? SurplusCase::query()
                    ->where('source_name', 'Osceola County Clerk')
                    ->where('county', 'Osceola')
                    ->where('surplus_availability', 'available')
                    ->whereNotNull('clerk_unique_key')
                    ->whereNotIn('clerk_unique_key', $seen)
                    ->lockForUpdate()->get()
                : collect();

            foreach ($removed as $case) {
                $case->update([
                    'surplus_availability' => 'no_longer_listed',
                    'last_surplus_research_run_id' => $run->id,
                    'updated_by' => $actor->id,
                ]);
            }
            $counts['removed_records'] = $removed->count();
            return $counts;
        }, 3);
    }

    private function toCents(string $amount): int
    {
        $normalized = str_replace([',', '$'], '', $amount);
        [$whole, $cents] = array_pad(explode('.', $normalized, 2), 2, '00');
        return ((int) $whole * 100) + (int) str_pad(substr($cents, 0, 2), 2, '0');
    }
}
