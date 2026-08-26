<?php

namespace App\Services;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\PreAuctionAcquisitionStatus;
use App\Enums\PreAuctionContactRole;
use App\Enums\PreAuctionEntitlementStatus;
use App\Models\PreAuctionAcquisition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PreAuctionAcquisitionService
{
    public function __construct(private readonly PropertyNormalizer $normalizer) {}

    public function create(array $data, User $actor): PreAuctionAcquisition
    {
        return DB::transaction(function () use ($data, $actor): PreAuctionAcquisition {
            $case = PreAuctionAcquisition::query()->create([
                ...$this->prepare($data, null, $actor), 'token' => (string) Str::uuid(),
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            $case->update(['case_number' => 'PAQ-'.now()->format('Y').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT)]);
            $this->syncPrimaryOwner($case, $actor);

            return $case->refresh();
        });
    }

    public function update(PreAuctionAcquisition $case, array $data, User $actor): PreAuctionAcquisition
    {
        return DB::transaction(function () use ($case, $data, $actor): PreAuctionAcquisition {
            $case->update([...$this->prepare($data, $case, $actor), 'updated_by' => $actor->id]);
            $this->syncPrimaryOwner($case, $actor);

            return $case->refresh();
        });
    }

    /** @param list<int> $caseIds */
    public function bulkUpdateStage(array $caseIds, PreAuctionAcquisitionStatus $status, User $actor): int
    {
        return DB::transaction(function () use ($caseIds, $status, $actor): int {
            $cases = PreAuctionAcquisition::query()
                ->whereKey($caseIds)
                ->lockForUpdate()
                ->get();

            if ($cases->count() !== count($caseIds)) {
                throw ValidationException::withMessages([
                    'case_ids' => 'One or more selected PreTax Auction files are no longer available.',
                ]);
            }

            foreach ($cases as $case) {
                Gate::forUser($actor)->authorize('update', $case);
                $case->update([
                    'status' => $status->value,
                    'updated_by' => $actor->id,
                ]);
            }

            return $cases->count();
        });
    }

    private function prepare(array $data, ?PreAuctionAcquisition $case, User $actor): array
    {
        $data['normalized_parcel_id'] = $this->normalizer->parcelId($data['parcel_id'] ?? $case?->parcel_id);
        $purchase = $data['purchase_price'] ?? $case?->purchase_price;
        $closing = $data['closing_costs'] ?? $case?->closing_costs;
        $other = $data['other_costs'] ?? $case?->other_costs;
        $total = round((float) ($purchase ?? 0) + (float) ($closing ?? 0) + (float) ($other ?? 0), 2);
        $projected = $data['projected_surplus'] ?? $case?->projected_surplus;
        $recovered = $data['amount_recovered'] ?? $case?->amount_recovered;
        $data['total_acquisition_cost'] = number_format($total, 2, '.', '');
        $data['projected_net'] = $projected !== null ? number_format(round((float) $projected - $total, 2), 2, '.', '') : null;
        $data['actual_net'] = $recovered !== null ? number_format(round((float) $recovered - $total, 2), 2, '.', '') : null;

        if (filled($data['non_redemption_reviewed_at'] ?? null)) $data['non_redemption_reviewed_by'] = $actor->id;
        $entitlement = $data['entitlement_status'] ?? $case?->entitlement_status?->value;
        if ($entitlement !== PreAuctionEntitlementStatus::NotReviewed->value) {
            $data['entitlement_reviewed_at'] ??= today()->toDateString();
            $data['entitlement_reviewed_by'] = $actor->id;
        } else {
            $data['entitlement_reviewed_at'] = null;
            $data['entitlement_reviewed_by'] = null;
        }

        return $data;
    }

    private function syncPrimaryOwner(PreAuctionAcquisition $case, User $actor): void
    {
        if (! $case->owner_contact_id) return;
        DB::table('contact_pre_auction_acquisition')->updateOrInsert(
            ['pre_auction_acquisition_id' => $case->id, 'contact_id' => $case->owner_contact_id],
            ['role' => PreAuctionContactRole::Owner->value, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('contact_pre_auction_acquisition')->where('pre_auction_acquisition_id', $case->id)
            ->where('contact_id', '!=', $case->owner_contact_id)->where('role', PreAuctionContactRole::Owner->value)
            ->update(['role' => PreAuctionContactRole::CoOwner->value, 'updated_at' => now()]);
    }
}
