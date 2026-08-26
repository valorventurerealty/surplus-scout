<?php

namespace App\Services;

use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SurplusContactMergeService
{
    /** @return Collection<int, array{first_name:string,last_name:string,count:int}> */
    public function duplicateGroups(): Collection
    {
        return Contact::query()
            ->where('type', ContactType::Surplus->value)
            ->whereNotNull('first_name')->whereNotNull('last_name')
            ->whereRaw("TRIM(first_name) <> ''")->whereRaw("TRIM(last_name) <> ''")
            ->selectRaw('LOWER(TRIM(first_name)) as first_name_key, LOWER(TRIM(last_name)) as last_name_key, MIN(first_name) as first_name, MIN(last_name) as last_name, COUNT(*) as duplicate_count')
            ->groupByRaw('LOWER(TRIM(first_name)), LOWER(TRIM(last_name))')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('last_name_key')->orderBy('first_name_key')
            ->get()
            ->map(fn ($group): array => [
                'first_name' => $group->first_name, 'last_name' => $group->last_name,
                'first_name_key' => $group->first_name_key, 'last_name_key' => $group->last_name_key,
                'count' => (int) $group->duplicate_count,
            ]);
    }

    /** @return Collection<int, Contact> */
    public function contactsForGroup(array $group, bool $lock = false): Collection
    {
        $query = Contact::query()->where('type', ContactType::Surplus->value)
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$group['first_name_key']])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [$group['last_name_key']])
            ->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    public function preview(array $group): array
    {
        $contacts = $this->contactsForGroup($group);
        $duplicateIds = $contacts->skip(1)->pluck('id');

        return [
            'name' => trim($contacts->first()?->full_name ?? $group['first_name'].' '.$group['last_name']),
            'canonical_id' => $contacts->first()?->id,
            'duplicate_ids' => $duplicateIds->all(),
            'surplus_cases' => DB::table('surplus_cases')->whereIn('claimant_contact_id', $duplicateIds)->count(),
            'tasks' => DB::table('tasks')->where('taskable_type', Contact::class)->whereIn('taskable_id', $duplicateIds)->count(),
        ];
    }

    public function mergeGroup(array $group): array
    {
        $contacts = $this->contactsForGroup($group, true);
        if ($contacts->count() < 2) {
            return ['name' => $group['first_name'].' '.$group['last_name'], 'canonical_id' => $contacts->first()?->id, 'merged_ids' => []];
        }

        $canonical = $contacts->shift();
        $duplicateIds = $contacts->pluck('id')->values();
        if (! $canonical || $duplicateIds->isEmpty()) {
            throw ValidationException::withMessages(['contacts' => 'The duplicate group changed before it could be merged.']);
        }

        $this->moveContactProperties($canonical->id, $duplicateIds);
        $this->moveContactDeals($canonical->id, $duplicateIds);
        $this->moveSurplusCaseContacts($canonical->id, $duplicateIds);

        foreach ([
            ['surplus_cases', 'claimant_contact_id'],
            ['surplus_intake_files', 'contact_id'],
            ['ai_surplus_csv_import_rows', 'matched_contact_id'],
            ['ai_surplus_csv_import_rows', 'contact_id'],
            ['properties', 'owner_contact_id'],
            ['contact_intake_files', 'contact_id'],
            ['property_financial_splits', 'contact_one_id'],
            ['property_financial_splits', 'contact_two_id'],
            ['negotiation_plans', 'buyer_contact_id'],
            ['armory_sessions', 'contact_id'],
            ['deals', 'primary_contact_id'],
        ] as [$table, $column]) {
            DB::table($table)->whereIn($column, $duplicateIds)->update([$column => $canonical->id]);
        }

        DB::table('tasks')->where('taskable_type', Contact::class)->whereIn('taskable_id', $duplicateIds)
            ->update(['taskable_id' => $canonical->id]);

        $mergedIds = $duplicateIds->all();
        $mergeNote = 'Merged duplicate Surplus contacts #'.implode(', #', $mergedIds).' into this contact on '.now()->toDateString().'.';
        $enrichment = [];
        foreach (['company', 'email', 'phone', 'mailing_address_line_1', 'mailing_address_line_2', 'mailing_city',
            'mailing_state_province', 'mailing_postal_code', 'mailing_country', 'assigned_user_id', 'next_follow_up_at',
            'next_follow_up_purpose'] as $field) {
            if (blank($canonical->{$field})) {
                $value = $contacts->pluck($field)->first(fn ($candidate): bool => filled($candidate));
                if (filled($value)) $enrichment[$field] = $value;
            }
        }
        $notes = collect([$canonical->notes, ...$contacts->pluck('notes')->all(), $mergeNote])
            ->filter(fn ($note): bool => filled($note))->unique()->implode("\n\n");
        $canonical->update([...$enrichment, 'notes' => $notes, 'updated_by' => $canonical->updated_by]);
        foreach ($contacts as $duplicate) {
            $duplicate->delete();
        }

        DB::table('audit_logs')->insert([
            'user_id' => null, 'event' => 'merged', 'auditable_type' => Contact::class,
            'auditable_id' => $canonical->id, 'old_values' => json_encode(['duplicate_contact_ids' => $mergedIds]),
            'new_values' => json_encode(['canonical_contact_id' => $canonical->id]), 'created_at' => now(),
        ]);

        return ['name' => $canonical->full_name, 'canonical_id' => $canonical->id, 'merged_ids' => $mergedIds];
    }

    private function moveContactProperties(int $canonicalId, Collection $duplicateIds): void
    {
        foreach (DB::table('contact_property')->whereIn('contact_id', $duplicateIds)->orderBy('id')->get() as $pivot) {
            DB::table('contact_property')->updateOrInsert(
                ['contact_id' => $canonicalId, 'property_id' => $pivot->property_id],
                ['relationship_type' => $pivot->relationship_type, 'created_by' => $pivot->created_by,
                    'created_at' => $pivot->created_at, 'updated_at' => now()],
            );
        }
        DB::table('contact_property')->whereIn('contact_id', $duplicateIds)->delete();
    }

    private function moveContactDeals(int $canonicalId, Collection $duplicateIds): void
    {
        foreach (DB::table('contact_deal')->whereIn('contact_id', $duplicateIds)->orderBy('id')->get() as $pivot) {
            DB::table('contact_deal')->updateOrInsert(
                ['deal_id' => $pivot->deal_id, 'contact_id' => $canonicalId, 'role' => $pivot->role],
                ['created_by' => $pivot->created_by, 'created_at' => $pivot->created_at, 'updated_at' => now()],
            );
        }
        DB::table('contact_deal')->whereIn('contact_id', $duplicateIds)->delete();
    }

    private function moveSurplusCaseContacts(int $canonicalId, Collection $duplicateIds): void
    {
        if (! Schema::hasTable('contact_surplus_case')) return;

        foreach (DB::table('contact_surplus_case')->whereIn('contact_id', $duplicateIds)->orderBy('id')->get() as $pivot) {
            $existing = DB::table('contact_surplus_case')->where('surplus_case_id', $pivot->surplus_case_id)
                ->where('contact_id', $canonicalId)->first();
            $role = $pivot->role === 'claimant' || $existing?->role === 'claimant' ? 'claimant' : $pivot->role;
            DB::table('contact_surplus_case')->updateOrInsert(
                ['surplus_case_id' => $pivot->surplus_case_id, 'contact_id' => $canonicalId],
                ['role' => $role, 'relationship_notes' => $existing?->relationship_notes ?: $pivot->relationship_notes,
                    'created_by' => $existing?->created_by ?? $pivot->created_by,
                    'created_at' => $existing?->created_at ?? $pivot->created_at, 'updated_at' => now()],
            );
        }
        DB::table('contact_surplus_case')->whereIn('contact_id', $duplicateIds)->delete();
    }
}
