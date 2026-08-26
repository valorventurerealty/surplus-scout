<?php

namespace App\Services;

use App\Models\SurplusCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SurplusCaseMergeService
{
    public function duplicateGroups(): Collection
    {
        return DB::table('surplus_cases')->whereNull('deleted_at')->whereNotNull('normalized_parcel_id')
            ->whereNotNull('claimant_contact_id')
            ->selectRaw('UPPER(TRIM(state)) as state_key, normalized_parcel_id, claimant_contact_id, COUNT(*) as duplicate_count')
            ->groupByRaw('UPPER(TRIM(state)), normalized_parcel_id, claimant_contact_id')
            ->havingRaw('COUNT(*) > 1')->orderBy('normalized_parcel_id')->get();
    }

    public function casesForGroup(object $group, bool $lock = false): Collection
    {
        $query = SurplusCase::query()->whereRaw('UPPER(TRIM(state)) = ?', [$group->state_key])
            ->where('normalized_parcel_id', $group->normalized_parcel_id)
            ->where('claimant_contact_id', $group->claimant_contact_id)->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    public function preview(object $group): array
    {
        $cases = $this->casesForGroup($group);

        return [
            'parcel' => $cases->first()?->parcel_id ?? $group->normalized_parcel_id,
            'claimant_contact_id' => $group->claimant_contact_id,
            'keep' => $cases->first()?->case_number,
            'archive' => $cases->skip(1)->pluck('case_number')->all(),
        ];
    }

    public function mergeGroup(object $group): array
    {
        $cases = $this->casesForGroup($group, true);
        $canonical = $cases->shift();
        if (! $canonical || $cases->isEmpty()) return ['kept' => $canonical?->case_number, 'archived' => []];
        $duplicateIds = $cases->pluck('id');
        $latest = $cases->concat([$canonical])->sortByDesc('updated_at')->first();
        $updates = [];
        foreach (['property_id', 'tax_deed_number', 'foreclosure_case_number', 'certificate_number', 'sale_date', 'claim_deadline', 'document_drive_url'] as $field) {
            if (blank($canonical->{$field})) {
                $value = $cases->pluck($field)->first(fn ($candidate): bool => filled($candidate));
                if (filled($value)) $updates[$field] = $value;
            }
        }
        foreach (['surplus_amount', 'agreed_fee_percentage', 'expected_fee', 'recovered_amount', 'actual_fee', 'paid_at'] as $field) {
            if (filled($latest?->{$field})) $updates[$field] = $latest->{$field};
        }
        if ($updates !== []) $canonical->update($updates);

        foreach (DB::table('contact_surplus_case')->whereIn('surplus_case_id', $duplicateIds)->orderBy('id')->get() as $link) {
            $existing = DB::table('contact_surplus_case')->where('surplus_case_id', $canonical->id)->where('contact_id', $link->contact_id)->first();
            DB::table('contact_surplus_case')->updateOrInsert(
                ['surplus_case_id' => $canonical->id, 'contact_id' => $link->contact_id],
                ['role' => $existing?->role === 'claimant' || $link->role === 'claimant' ? 'claimant' : $link->role,
                    'relationship_notes' => $existing?->relationship_notes ?: $link->relationship_notes,
                    'created_by' => $existing?->created_by ?? $link->created_by,
                    'created_at' => $existing?->created_at ?? $link->created_at, 'updated_at' => now()],
            );
        }
        DB::table('contact_surplus_case')->whereIn('surplus_case_id', $duplicateIds)->delete();
        DB::table('surplus_intake_files')->whereIn('surplus_case_id', $duplicateIds)->update(['surplus_case_id' => $canonical->id]);
        DB::table('ai_surplus_csv_import_rows')->whereIn('matched_surplus_case_id', $duplicateIds)->update(['matched_surplus_case_id' => $canonical->id]);
        DB::table('ai_surplus_csv_import_rows')->whereIn('surplus_case_id', $duplicateIds)->update(['surplus_case_id' => $canonical->id]);
        DB::table('tasks')->where('taskable_type', SurplusCase::class)->whereIn('taskable_id', $duplicateIds)->update(['taskable_id' => $canonical->id]);
        $this->archiveDuplicateTasks($canonical);

        $archived = $cases->pluck('case_number')->all();
        foreach ($cases as $duplicate) $duplicate->delete();
        DB::table('audit_logs')->insert([
            'user_id' => null, 'event' => 'merged', 'auditable_type' => SurplusCase::class,
            'auditable_id' => $canonical->id, 'old_values' => json_encode(['duplicate_case_ids' => $duplicateIds->all()]),
            'new_values' => json_encode(['canonical_case_id' => $canonical->id]), 'created_at' => now(),
        ]);

        return ['kept' => $canonical->case_number, 'archived' => $archived];
    }

    private function archiveDuplicateTasks(SurplusCase $case): void
    {
        $tasks = DB::table('tasks')->whereNull('deleted_at')->where('taskable_type', SurplusCase::class)
            ->where('taskable_id', $case->id)->orderBy('id')->get()->groupBy(fn ($task): string => mb_strtolower(trim($task->title)));
        foreach ($tasks as $matches) {
            $duplicateIds = $matches->skip(1)->pluck('id');
            if ($duplicateIds->isNotEmpty()) DB::table('tasks')->whereIn('id', $duplicateIds)->update(['deleted_at' => now(), 'updated_at' => now()]);
        }
    }
}
