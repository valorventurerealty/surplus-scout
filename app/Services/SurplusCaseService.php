<?php

namespace App\Services;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\SurplusCaseStatus;
use App\Models\SurplusCase;
use App\Models\User;
use App\Enums\SurplusContactRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SurplusCaseService
{
    public const FIXED_FEE_PERCENTAGE = 12.0;

    public const MAX_FEE_PERCENTAGE = self::FIXED_FEE_PERCENTAGE;

    public function __construct(private readonly PropertyNormalizer $propertyNormalizer) {}

    public function create(array $data, User $actor): SurplusCase
    {
        return DB::transaction(function () use ($data, $actor): SurplusCase {
            $case = SurplusCase::query()->create([
                ...$this->prepare($data), 'token' => (string) Str::uuid(),
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $case->update(['case_number' => 'SUR-'.now()->format('Y').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT)]);
            $this->syncPrimaryClaimant($case, $actor);

            return $case->refresh();
        });
    }

    public function update(SurplusCase $case, array $data, User $actor): SurplusCase
    {
        return DB::transaction(function () use ($case, $data, $actor): SurplusCase {
            $case->update([...$this->prepare($data, $case), 'updated_by' => $actor->id]);
            $this->syncPrimaryClaimant($case, $actor);

            return $case->refresh();
        });
    }

    /** @param list<int> $caseIds */
    public function bulkUpdateStage(array $caseIds, SurplusCaseStatus $status, User $actor): int
    {
        return $this->bulkUpdate($caseIds, $actor, function (SurplusCase $case) use ($status, $actor): void {
            $changes = [
                'status' => $status->value,
                'updated_by' => $actor->id,
            ];

            if ($status === SurplusCaseStatus::Paid && $case->paid_at === null) {
                $changes['paid_at'] = today()->toDateString();
            }

            $case->update($changes);
        });
    }

    /** @param list<int> $caseIds */
    public function bulkUpdateCounty(array $caseIds, string $county, User $actor): int
    {
        return DB::transaction(function () use ($caseIds, $county, $actor): int {
            $cases = SurplusCase::query()
                ->whereKey($caseIds)
                ->lockForUpdate()
                ->get();

            if ($cases->count() !== count($caseIds)) {
                throw ValidationException::withMessages([
                    'case_ids' => 'One or more selected Surplus cases are no longer available.',
                ]);
            }

            $this->validateBulkCountyIdentifiers($cases, $county);

            foreach ($cases as $case) {
                Gate::forUser($actor)->authorize('update', $case);
                $case->update(['county' => $county, 'updated_by' => $actor->id]);
            }

            return $cases->count();
        });
    }

    /** @param list<int> $caseIds */
    private function bulkUpdate(array $caseIds, User $actor, callable $update): int
    {
        return DB::transaction(function () use ($caseIds, $actor, $update): int {
            $cases = SurplusCase::query()->whereKey($caseIds)->lockForUpdate()->get();

            if ($cases->count() !== count($caseIds)) {
                throw ValidationException::withMessages([
                    'case_ids' => 'One or more selected Surplus cases are no longer available.',
                ]);
            }

            foreach ($cases as $case) {
                Gate::forUser($actor)->authorize('update', $case);
                $update($case);
            }

            return $cases->count();
        });
    }

    private function validateBulkCountyIdentifiers(\Illuminate\Support\Collection $cases, string $county): void
    {
        $selectedIds = $cases->modelKeys();
        $seen = [];

        foreach ($cases as $case) {
            foreach (['tax_deed_number', 'foreclosure_case_number'] as $field) {
                if (blank($case->{$field}) || blank($case->state)) {
                    continue;
                }

                $key = strtolower($case->state.'|'.$county.'|'.$field.'|'.$case->{$field});
                $conflict = isset($seen[$key]) || SurplusCase::query()
                    ->whereNotIn('id', $selectedIds)
                    ->where('state', $case->state)
                    ->where('county', $county)
                    ->where($field, $case->{$field})
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'county' => 'That county change would duplicate an existing Surplus case identifier.',
                    ]);
                }

                $seen[$key] = true;
            }
        }
    }

    private function prepare(array $data, ?SurplusCase $case = null): array
    {
        if (array_key_exists('parcel_id', $data)) {
            $data['normalized_parcel_id'] = $this->propertyNormalizer->parcelId($data['parcel_id']);
        }

        if (($data['status'] ?? null) === \App\Enums\SurplusCaseStatus::Paid->value
            && blank($data['paid_at'] ?? $case?->paid_at)) {
            $data['paid_at'] = today()->toDateString();
        }
        $percentage = self::FIXED_FEE_PERCENTAGE;
        $data['agreed_fee_percentage'] = number_format($percentage, 2, '.', '');

        $surplus = $data['surplus_amount'] ?? $case?->surplus_amount;
        $recovered = $data['recovered_amount'] ?? $case?->recovered_amount;
        $actualFee = $data['actual_fee'] ?? $case?->actual_fee;
        $actualFeeBase = $recovered ?? $surplus;
        if ($actualFee !== null && $actualFeeBase !== null) {
            $maximumActualFee = round((float) $actualFeeBase * self::MAX_FEE_PERCENTAGE) / 100;
            if ((float) $actualFee > $maximumActualFee) {
                throw ValidationException::withMessages([
                    'actual_fee' => 'The actual fee may not exceed 12% of the recovered amount or listed surplus.',
                ]);
            }
        }

        $data['expected_fee'] = $surplus !== null
            ? number_format(round((float) $surplus * $percentage) / 100, 2, '.', '')
            : null;

        return $data;
    }

    private function syncPrimaryClaimant(SurplusCase $case, User $actor): void
    {
        if (! $case->claimant_contact_id || ! DB::getSchemaBuilder()->hasTable('contact_surplus_case')) return;

        DB::table('contact_surplus_case')->updateOrInsert(
            ['surplus_case_id' => $case->id, 'contact_id' => $case->claimant_contact_id],
            ['role' => SurplusContactRole::Claimant->value, 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('contact_surplus_case')->where('surplus_case_id', $case->id)
            ->where('contact_id', '!=', $case->claimant_contact_id)
            ->where('role', SurplusContactRole::Claimant->value)
            ->update(['role' => SurplusContactRole::Other->value, 'updated_at' => now()]);
    }
}
