<?php

namespace App\Services\SurplusResearch;

use App\Contracts\SurplusResearch\CountyOwnerResearchProviderInterface;
use App\Data\SurplusResearch\TrimNoticeData;
use App\Enums\SurplusOwnerResearchStatus;
use App\Enums\SurplusOwnerType;
use App\Exceptions\OwnerResearchException;
use App\Models\SurplusCase;
use App\Models\SurplusOwnerResearchAttempt;
use App\Models\SurplusOwnerResearchBatch;
use App\Models\SurplusOwnerResearchEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SurplusOwnerResearchService
{
    public function __construct(
        private readonly CountyOwnerResearchProviderInterface $provider,
        private readonly OwnerNameNormalizer $names,
        private readonly OwnerComparisonService $comparison,
        private readonly OwnerClassificationService $classification,
        private readonly SurplusOwnerResearchBatchService $batches,
    ) {}

    public function research(SurplusCase $case, SurplusOwnerResearchBatch $batch, User $actor): void
    {
        $attempt = $this->startAttempt($case, $batch);
        $checkedYears = [];
        try {
            $this->setStatus($case, SurplusOwnerResearchStatus::ResearchingProperty, $actor);
            $this->event($attempt, 'Property Appraiser Search Started', ['parcel' => $case->normalized_parcel_id]);
            $property = $this->provider->findProperty((string) $case->normalized_parcel_id);
            $attempt->update([
                'parcel_returned' => $property->parcelRaw, 'current_owner_found' => $property->currentOwnerRaw,
                'property_source_reference' => $property->sourceReference,
            ]);
            $this->event($attempt, 'Parcel Verified', ['parcel' => $property->parcelRaw]);
            $this->event($attempt, 'Current Owner Found', ['owner' => $property->currentOwnerRaw]);

            $this->setStatus($case, SurplusOwnerResearchStatus::ResearchingHistoricalOwner, $actor);
            $primaryYear = (int) config('surplus_research.owner_research.primary_trim_year', 2025);
            $fallbackYear = (int) config('surplus_research.owner_research.fallback_trim_year', 2024);
            $primary = $this->provider->findTrimNotice($property->parcelNormalized, $primaryYear);
            $checkedYears[] = $primaryYear;
            if ($primary) $this->event($attempt, $primaryYear.' TRIM Opened', ['source' => $primary->sourceReference]);

            $selected = null;
            $warning = null;
            if ($primary && ! $this->comparison->equivalent($property->currentOwnerRaw, $primary->ownerRaw)) {
                $selected = $primary;
            } else {
                if ($primary) $this->event($attempt, $primaryYear.' Owner Matched Current Owner');
                else $warning = "The {$primaryYear} TRIM notice was unavailable; the configured fallback was checked.";
                $fallback = $this->provider->findTrimNotice($property->parcelNormalized, $fallbackYear);
                $checkedYears[] = $fallbackYear;
                if ($fallback) $this->event($attempt, $fallbackYear.' TRIM Opened', ['source' => $fallback->sourceReference]);
                if (! $fallback) {
                    throw new OwnerResearchException('Neither configured TRIM year produced a usable historical notice.', SurplusOwnerResearchStatus::TrimNoticeNotFound);
                }
                if ($this->comparison->equivalent($property->currentOwnerRaw, $fallback->ownerRaw)) {
                    throw new OwnerResearchException(
                        $primary
                            ? 'Current Property Appraiser owner matches both available historical TRIM notices. Historical surplus claimant could not be differentiated automatically.'
                            : "The {$primaryYear} TRIM was unavailable and the current owner matches the {$fallbackYear} TRIM. Historical surplus claimant could not be differentiated automatically.",
                        SurplusOwnerResearchStatus::OwnerMatchUnresolved,
                    );
                }
                $selected = $fallback;
            }

            $ownerType = $this->classification->classify($selected->ownerRaw, $selected->coOwnerRaw);
            $finalStatus = $this->statusForOwnerType($ownerType);
            $filePath = $this->storeTrim($case, $selected);
            $note = 'Public-record research identified a potential claimant/research subject only. It does not determine legal entitlement, heirship, current residence, or payment eligibility.';
            if ($warning || $selected->warning) $note .= ' '.trim(($warning ?? '').' '.($selected->warning ?? ''));

            DB::transaction(function () use ($case, $actor, $property, $selected, $ownerType, $finalStatus, $note, $attempt, $checkedYears, $filePath): void {
                $locked = SurplusCase::query()->lockForUpdate()->findOrFail($case->id);
                $locked->update([
                    'research_status' => $finalStatus->value,
                    'current_owner_raw' => $property->currentOwnerRaw,
                    'current_owner_normalized' => $this->names->normalize($property->currentOwnerRaw),
                    'previous_owner_raw' => $selected->ownerRaw,
                    'previous_owner_normalized' => $this->names->normalize($selected->ownerRaw),
                    'co_owner_raw' => $selected->coOwnerRaw,
                    'claimant_mailing_address' => $selected->mailingAddress,
                    'claimant_mailing_city' => $selected->mailingCity,
                    'claimant_mailing_state' => $selected->mailingState,
                    'claimant_mailing_zip' => $selected->mailingZip,
                    'historical_trim_year' => $selected->year,
                    'property_appraiser_address' => $property->propertyAddress,
                    'owner_type' => $ownerType->value,
                    'property_appraiser_verified' => true,
                    'historical_owner_verified' => true,
                    'owner_researched_at' => now(), 'owner_research_notes' => $note,
                    'updated_by' => $actor->id,
                ]);
                $attempt->update([
                    'status' => $finalStatus->value, 'trim_years_checked' => $checkedYears,
                    'selected_trim_year' => $selected->year, 'historical_owner_found' => $selected->ownerRaw,
                    'classification' => $ownerType->value, 'trim_source_reference' => $selected->sourceReference,
                    'trim_file_disk' => 'local', 'trim_file_path' => $filePath, 'trim_file_hash' => $selected->fileHash,
                    'extracted_text_excerpt' => Str::limit($selected->extractedText, 12000, ''),
                    'extraction_warning' => $selected->warning, 'research_notes' => $note, 'completed_at' => now(),
                ]);
                $this->event($attempt, 'Historical Owner Found', ['year' => $selected->year, 'owner' => $selected->ownerRaw]);
                $this->event($attempt, 'Owner Classified', ['classification' => $ownerType->value]);
                $this->event($attempt, 'Case Updated', ['research_status' => $finalStatus->value]);
            }, 3);
            $this->batches->recordTerminalResult($batch, $finalStatus->value, true);
        } catch (OwnerResearchException $error) {
            $this->recordFailure($case, $attempt, $actor, $error, $checkedYears);
            if ($error->retryable) throw $error;
            $this->batches->recordTerminalResult($batch, $error->researchStatus->value, false);
        } catch (\Throwable $error) {
            $wrapped = new OwnerResearchException('The Property Appraiser workflow failed unexpectedly.', SurplusOwnerResearchStatus::PropertyAppraiserError, true, $error);
            $this->recordFailure($case, $attempt, $actor, $wrapped, $checkedYears);
            throw $wrapped;
        }
    }

    private function startAttempt(SurplusCase $case, SurplusOwnerResearchBatch $batch): SurplusOwnerResearchAttempt
    {
        return DB::transaction(function () use ($case, $batch): SurplusOwnerResearchAttempt {
            $number = (int) SurplusOwnerResearchAttempt::query()->where('surplus_case_id', $case->id)->lockForUpdate()->max('attempt_number') + 1;
            return SurplusOwnerResearchAttempt::query()->create([
                'surplus_owner_research_batch_id' => $batch->id, 'surplus_case_id' => $case->id,
                'attempt_number' => $number, 'status' => 'running',
                'parcel_searched' => (string) ($case->normalized_parcel_id ?: $case->parcel_id), 'started_at' => now(),
            ]);
        });
    }

    private function recordFailure(SurplusCase $case, SurplusOwnerResearchAttempt $attempt, User $actor, OwnerResearchException $error, array $years): void
    {
        DB::transaction(function () use ($case, $attempt, $actor, $error, $years): void {
            SurplusCase::query()->whereKey($case->id)->update([
                'research_status' => $error->researchStatus->value,
                'owner_research_notes' => $error->getMessage(), 'updated_by' => $actor->id,
            ]);
            $attempt->update([
                'status' => $error->researchStatus->value, 'trim_years_checked' => $years,
                'browser_error' => $error->researchStatus === SurplusOwnerResearchStatus::PropertyAppraiserError ? $error->getMessage() : null,
                'extraction_warning' => $error->researchStatus !== SurplusOwnerResearchStatus::PropertyAppraiserError ? $error->getMessage() : null,
                'research_notes' => $error->getMessage(), 'diagnostic_reference' => 'attempt:'.$attempt->id,
                'completed_at' => now(),
            ]);
            $this->event($attempt, 'Research Stopped', ['status' => $error->researchStatus->value, 'message' => $error->getMessage()]);
        });
    }

    private function setStatus(SurplusCase $case, SurplusOwnerResearchStatus $status, User $actor): void
    {
        $case->forceFill(['research_status' => $status->value, 'updated_by' => $actor->id])->save();
    }

    private function statusForOwnerType(SurplusOwnerType $type): SurplusOwnerResearchStatus
    {
        return match ($type) {
            SurplusOwnerType::Individual, SurplusOwnerType::MultipleIndividuals => SurplusOwnerResearchStatus::ReadyForSkipTrace,
            SurplusOwnerType::Business => SurplusOwnerResearchStatus::BusinessResearchNeeded,
            SurplusOwnerType::Estate => SurplusOwnerResearchStatus::EstateHeirResearchNeeded,
            SurplusOwnerType::Trust => SurplusOwnerResearchStatus::TrustResearchNeeded,
            SurplusOwnerType::GovernmentAssociation, SurplusOwnerType::Unknown => SurplusOwnerResearchStatus::ManualReview,
        };
    }

    private function storeTrim(SurplusCase $case, TrimNoticeData $trim): string
    {
        $path = 'surplus-research/osceola/owner-research/'.$case->token.'/'.$trim->year.'-'.$trim->fileHash.'.pdf';
        if (! Storage::disk('local')->put($path, $trim->pdfContents)) {
            throw new OwnerResearchException('The TRIM notice could not be stored privately.', SurplusOwnerResearchStatus::PropertyAppraiserError, true);
        }
        return $path;
    }

    private function event(SurplusOwnerResearchAttempt $attempt, string $event, array $context = []): void
    {
        SurplusOwnerResearchEvent::query()->create([
            'surplus_owner_research_attempt_id' => $attempt->id, 'event' => $event,
            'context' => $context === [] ? null : $context, 'occurred_at' => now(),
        ]);
    }
}
