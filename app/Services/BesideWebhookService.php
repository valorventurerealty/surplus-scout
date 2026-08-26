<?php

namespace App\Services;

use App\Domain\Contacts\ContactNormalizer;
use App\Enums\PhoneInteractionMatchStatus;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\PhoneInteraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BesideWebhookService
{
    public function __construct(private readonly ContactNormalizer $normalizer) {}

    /** @return array{interaction: PhoneInteraction, created: bool, updated: bool} */
    public function receive(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $existing = PhoneInteraction::query()
                ->where('provider', 'beside')
                ->where('provider_event_id', $data['event_id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $changes = $this->enrichmentChanges($existing, $data);

                if ($changes !== []) {
                    $existing->update($changes + ['received_at' => now()]);
                    $this->audit($existing, 'beside_enriched', [
                        'updated_fields' => array_keys($changes),
                    ]);
                }

                return [
                    'interaction' => $existing->refresh(),
                    'created' => false,
                    'updated' => $changes !== [],
                ];
            }

            $normalizedPhone = $this->normalizer->phone($data['phone_number'] ?? null);
            $matches = $normalizedPhone
                ? Contact::query()->where('normalized_phone', $normalizedPhone)->limit(2)->get()
                : collect();

            $contact = $matches->count() === 1 ? $matches->first() : null;
            $matchStatus = match (true) {
                $contact !== null => PhoneInteractionMatchStatus::Matched,
                $matches->count() > 1 => PhoneInteractionMatchStatus::Conflicting,
                default => PhoneInteractionMatchStatus::Unmatched,
            };

            $interaction = PhoneInteraction::query()->firstOrCreate([
                'provider' => 'beside',
                'provider_event_id' => $data['event_id'],
            ], [
                'token' => (string) Str::uuid(),
                'event_type' => $data['event_type'],
                'direction' => $data['direction'],
                'contact_id' => $contact?->id,
                'match_status' => $matchStatus,
                'caller_phone' => $data['phone_number'] ?? null,
                'normalized_phone' => $normalizedPhone,
                'caller_name' => $data['caller_name'] ?? null,
                'caller_email' => $data['caller_email'] ?? null,
                'caller_company' => $data['caller_company'] ?? null,
                'inbox' => $data['inbox'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'duration_seconds' => $data['duration_seconds'] ?? null,
                'summary' => $data['summary'] ?? null,
                'transcript' => $data['transcript'] ?? null,
                'recording_url' => $data['recording_url'] ?? null,
                'action_items' => $data['action_items'] ?? null,
                'provider_payload' => $data['provider_payload'] ?? null,
                'received_at' => now(),
            ]);

            if ($interaction->wasRecentlyCreated) {
                $this->audit($interaction, 'beside_received', [
                    'provider' => $interaction->provider,
                    'provider_event_id' => $interaction->provider_event_id,
                    'event_type' => $interaction->event_type->value,
                    'match_status' => $interaction->match_status->value,
                    'contact_id' => $interaction->contact_id,
                ]);
            }

            return [
                'interaction' => $interaction,
                'created' => $interaction->wasRecentlyCreated,
                'updated' => false,
            ];
        });
    }

    /** @return array<string, mixed> */
    private function enrichmentChanges(PhoneInteraction $interaction, array $data): array
    {
        $changes = [];
        $fields = [
            'caller_name' => 'caller_name',
            'caller_email' => 'caller_email',
            'caller_company' => 'caller_company',
            'inbox' => 'inbox',
            'summary' => 'summary',
            'transcript' => 'transcript',
            'recording_url' => 'recording_url',
        ];

        foreach ($fields as $input => $attribute) {
            $value = $data[$input] ?? null;

            if (is_string($value) && trim($value) !== '' && $interaction->{$attribute} !== trim($value)) {
                $changes[$attribute] = trim($value);
            }
        }

        if (array_key_exists('duration_seconds', $data) && $data['duration_seconds'] !== null
            && $interaction->duration_seconds !== (int) $data['duration_seconds']) {
            $changes['duration_seconds'] = (int) $data['duration_seconds'];
        }

        if (! empty($data['action_items']) && $interaction->action_items !== array_values($data['action_items'])) {
            $changes['action_items'] = array_values($data['action_items']);
        }

        if (! empty($data['provider_payload'])) {
            $payload = array_replace_recursive($interaction->provider_payload ?? [], $data['provider_payload']);
            if ($payload !== ($interaction->provider_payload ?? [])) {
                $changes['provider_payload'] = $payload;
            }
        }

        if (filled($data['phone_number'] ?? null)) {
            $phone = trim((string) $data['phone_number']);
            $normalizedPhone = $this->normalizer->phone($phone);
            if ($interaction->caller_phone !== $phone) {
                $changes['caller_phone'] = $phone;
            }
            if ($interaction->normalized_phone !== $normalizedPhone) {
                $changes['normalized_phone'] = $normalizedPhone;
            }
        }

        return $changes;
    }

    /** @param array<string, mixed> $values */
    private function audit(PhoneInteraction $interaction, string $event, array $values): void
    {
        $request = app()->runningInConsole() ? null : request();
        AuditLog::query()->create([
            'user_id' => null,
            'event' => $event,
            'auditable_type' => $interaction->getMorphClass(),
            'auditable_id' => $interaction->getKey(),
            'new_values' => $values,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
