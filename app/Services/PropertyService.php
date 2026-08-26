<?php

namespace App\Services;

use App\Domain\Properties\PropertyNormalizer;
use App\Models\Property;
use App\Models\PropertyIntakeFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PropertyService
{
    public function __construct(
        private readonly PropertyNormalizer $normalizer,
        private readonly PropertyFinancialCalculator $financialCalculator,
        private readonly PropertyFinancialDependencySynchronizer $dependencySynchronizer,
        private readonly PropertyChecklistService $checklistService,
    ) {}

    public function create(array $data, User $actor): Property
    {
        return DB::transaction(function () use ($data, $actor): Property {
            $intake = null;
            if (filled($data['intake_token'] ?? null)) {
                $intake = PropertyIntakeFile::query()
                    ->where('token', $data['intake_token'])
                    ->where('user_id', $actor->id)
                    ->where('status', 'ready')
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()
                    ->first();

                if (! $intake) {
                    throw ValidationException::withMessages(['intake_token' => 'This extraction has expired or is no longer available.']);
                }
            }

            $property = Property::query()->create([
                ...$this->prepare($data),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->checklistService->initialize($property, $actor);

            if ($intake) {
                $intake->update([
                    'property_id' => $property->id,
                    'status' => 'attached',
                    'attached_at' => now(),
                    'expires_at' => null,
                ]);

                if ($intake->ai_conversation_id) {
                    $conversation = $intake->aiConversation()->lockForUpdate()->first();
                    $conversation?->update([
                        'status' => 'completed',
                        'result_json' => ['property_id' => $property->id],
                        'last_message_at' => now(),
                    ]);
                    $conversation?->messages()->create([
                        'role' => 'assistant',
                        'content' => 'Completed successfully: the property was created, its standard checklist was initialized, and the approved source was attached privately.',
                        'metadata_json' => ['property_id' => $property->id],
                    ]);
                }
            }

            return $property;
        });
    }

    public function update(Property $property, array $data, User $actor): Property
    {
        return DB::transaction(function () use ($property, $data, $actor): Property {
            $property->update([...$this->prepare($data, $property), 'updated_by' => $actor->id]);
            $this->dependencySynchronizer->synchronize($property->refresh(), $actor);

            return $property->refresh();
        });
    }

    private function prepare(array $data, ?Property $property = null): array
    {
        $data['normalized_parcel_id'] = $this->normalizer->parcelId($data['parcel_id'] ?? null);
        $data['normalized_county'] = $this->normalizer->county($data['county']);
        $data['normalized_address'] = $this->normalizer->address(
            $data['address'], $data['city'], $data['state'], $data['postal_code'] ?? null
        );
        $data['gis_links'] = collect(preg_split('/\R/', (string) ($data['gis_links_text'] ?? '')))
            ->map(fn (string $url) => trim($url))->filter()->unique()->values()->all();

        if ($this->financialCalculator->hasInput($data)) {
            $data = [...$data, ...$this->financialCalculator->calculate($data, $property)];
        }

        unset($data['gis_links_text'], $data['intake_token'], $data['approve_extracted_data']);

        return $data;
    }
}
