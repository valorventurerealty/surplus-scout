<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ContactService
{
    public function create(array $data, User $actor): Contact
    {
        return DB::transaction(function () use ($data, $actor): Contact {
            $syncProperties = (bool) Arr::pull($data, 'property_assignments_present', false);
            $propertyIds = (array) Arr::pull($data, 'property_ids', []);
            $contact = Contact::query()->create([
                ...$data,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($syncProperties) {
                $this->syncProperties($contact, $propertyIds, $actor);
            }

            return $contact;
        });
    }

    public function update(Contact $contact, array $data, User $actor): Contact
    {
        return DB::transaction(function () use ($contact, $data, $actor): Contact {
            $syncProperties = (bool) Arr::pull($data, 'property_assignments_present', false);
            $propertyIds = (array) Arr::pull($data, 'property_ids', []);
            $contact->update([...$data, 'updated_by' => $actor->id]);

            if ($syncProperties) {
                $this->syncProperties($contact, $propertyIds, $actor);
            }

            return $contact->refresh();
        });
    }

    private function syncProperties(Contact $contact, array $propertyIds, User $actor): void
    {
        $before = $contact->properties()->pluck('properties.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $after = collect($propertyIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        $contact->properties()->detach(array_values(array_diff($before, $after)));
        $contact->properties()->attach(collect(array_diff($after, $before))->mapWithKeys(fn ($propertyId) => [$propertyId => [
            'relationship_type' => 'associated',
            'created_by' => $actor->id,
        ]])->all());

        if ($before !== $after) {
            $request = app()->runningInConsole() ? null : request();
            AuditLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'property_assignments_updated',
                'auditable_type' => $contact->getMorphClass(),
                'auditable_id' => $contact->getKey(),
                'old_values' => ['property_ids' => $before],
                'new_values' => ['property_ids' => $after],
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        }
    }
}
