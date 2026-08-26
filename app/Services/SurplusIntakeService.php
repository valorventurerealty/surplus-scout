<?php

namespace App\Services;

use App\Contracts\SurplusDocumentExtractionInterface;
use App\Data\SurplusExtractionResult;
use App\Domain\Properties\PropertyNormalizer;
use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\SurplusCaseStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WetlandsStatus;
use App\Models\AiConversation;
use App\Models\Contact;
use App\Models\Property;
use App\Models\PropertyTaxRecord;
use App\Models\SurplusCase;
use App\Models\SurplusIntakeFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SurplusIntakeService
{
    private const MONEY_FIELDS = [
        'market_value', 'assessed_value', 'taxable_value', 'prior_year_final_tax',
        'proposed_tax', 'no_budget_change_tax', 'non_ad_valorem_assessments', 'surplus_amount',
    ];

    public function __construct(
        private readonly SurplusDocumentExtractionInterface $extractor,
        private readonly PropertyNormalizer $propertyNormalizer,
        private readonly PropertyChecklistService $checklistService,
        private readonly SurplusCaseService $surplusCaseService,
    ) {}

    public function extract(string $prompt, UploadedFile $upload, User $user, AiConversation $conversation): SurplusIntakeFile
    {
        abort_unless($conversation->user_id === $user->id, 403);
        $content = $upload->getContent();
        $hash = hash('sha256', $content);
        $fingerprint = hash('sha256', implode('|', [$hash, mb_strtolower(trim($prompt)), config('ai.provider'), config('ai.extraction_model'), 'surplus-v1']));
        $cached = SurplusIntakeFile::query()->where('user_id', $user->id)->where('request_fingerprint', $fingerprint)
            ->whereIn('status', ['ready', 'attached'])->whereNotNull('extraction_json')->latest()->first();
        $token = (string) Str::uuid();
        $extension = strtolower($upload->getClientOriginalExtension());
        $path = 'surplus-intakes/'.$user->id.'/'.$token.'.'.$extension;

        if (! Storage::disk('local')->put($path, $content)) {
            throw ValidationException::withMessages(['document' => 'The Surplus source could not be saved to private storage.']);
        }

        try {
            $intake = SurplusIntakeFile::query()->create([
                'token' => $token, 'user_id' => $user->id, 'ai_conversation_id' => $conversation->id,
                'disk' => 'local', 'path' => $path,
                'original_name' => Str::limit(basename($upload->getClientOriginalName()), 255, ''),
                'mime_type' => $upload->getMimeType() ?: 'application/octet-stream', 'size_bytes' => strlen($content),
                'sha256' => $hash, 'request_fingerprint' => $fingerprint, 'status' => $cached ? 'ready' : 'processing',
                'user_prompt' => $prompt, 'provider' => config('ai.provider'), 'model' => config('ai.extraction_model'),
                'provider_response_id' => $cached?->provider_response_id, 'extraction_json' => $cached?->extraction_json,
                'input_tokens' => $cached?->input_tokens, 'output_tokens' => $cached?->output_tokens,
                'expires_at' => now()->addHours((int) config('ai.surplus_intake_expiration_hours', 24)),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        if ($cached) {
            return $intake->refresh();
        }

        $startedAt = microtime(true);
        try {
            $result = $this->extractor->extract($intake);
            $intake->update([
                'status' => 'ready', 'provider_response_id' => $result->responseId,
                'extraction_json' => $result->toArray(), 'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens,
            ]);
            DB::table('ai_usage_records')->insert([
                'conversation_id' => $conversation->id, 'user_id' => $user->id,
                'provider' => (string) config('ai.provider'), 'model' => (string) config('ai.extraction_model'),
                'operation' => 'surplus_document_extraction', 'input_tokens' => $result->inputTokens,
                'output_tokens' => $result->outputTokens, 'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'successful' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $parts = explode(':', $exception->getMessage(), 2);
            $intake->update([
                'status' => 'failed', 'error_code' => Str::limit($parts[0] ?: 'extraction_failed', 80, ''),
                'error_message' => Str::limit(trim($parts[1] ?? $exception->getMessage()), 1000, ''),
            ]);
            DB::table('ai_usage_records')->insert([
                'conversation_id' => $conversation->id, 'user_id' => $user->id,
                'provider' => (string) config('ai.provider'), 'model' => (string) config('ai.extraction_model'),
                'operation' => 'surplus_document_extraction', 'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'successful' => false, 'error_code' => Str::limit($parts[0] ?: 'extraction_failed', 100, ''),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            report($exception);
            throw ValidationException::withMessages(['document' => trim($parts[1] ?? $exception->getMessage())]);
        }

        return $intake->refresh();
    }

    public function review(SurplusIntakeFile $intake, User $user): array
    {
        abort_unless($intake->user_id === $user->id && $intake->status === 'ready', 403);
        $result = SurplusExtractionResult::fromArray($intake->extraction_json ?? []);
        $values = $this->normalizeValues($result);

        return [
            'values' => $values,
            'summary' => [
                'file_name' => $intake->original_name, 'fields' => $result->fields,
                'missing_fields' => $result->missingFields, 'warnings' => array_values(array_unique([
                    ...$result->warnings,
                    'Annual tax-history amounts will not be copied into the property acquisition-cost Taxes field.',
                    'The source does not prove a surplus amount unless that amount is explicitly printed in the document.',
                ])),
                'property_duplicates' => $this->propertyDuplicates($values),
                'contact_duplicates' => $this->contactDuplicates($values),
                'surplus_duplicates' => $this->surplusDuplicates($values),
                'document_duplicates' => $this->documentDuplicates($intake),
            ],
        ];
    }

    public function execute(array $data, User $user, AiConversation $conversation): array
    {
        return DB::transaction(function () use ($data, $user, $conversation): array {
            $intake = SurplusIntakeFile::query()->where('token', $data['intake_token'])->where('user_id', $user->id)
                ->where('status', 'ready')->where('expires_at', '>', now())->lockForUpdate()->first();
            if (! $intake || $intake->ai_conversation_id !== $conversation->id) {
                throw ValidationException::withMessages(['intake_token' => 'This Surplus extraction expired or does not belong to this AI task.']);
            }

            Gate::forUser($user)->authorize('create', Property::class);
            Gate::forUser($user)->authorize('create', Contact::class);
            Gate::forUser($user)->authorize('create', SurplusCase::class);

            $contact = $data['contact_resolution'] === 'use_existing'
                ? Contact::query()->lockForUpdate()->findOrFail($data['contact_id'])
                : $this->createContact($data, $user);
            Gate::forUser($user)->authorize('view', $contact);

            $property = $data['property_resolution'] === 'use_existing'
                ? Property::query()->lockForUpdate()->findOrFail($data['property_id'])
                : $this->createProperty($data, $contact, $user);
            Gate::forUser($user)->authorize('view', $property);

            if (! $property->owner_contact_id && $contact) {
                $property->update(['owner_contact_id' => $contact->id, 'updated_by' => $user->id]);
            }
            $property->contacts()->syncWithoutDetaching([$contact->id => ['relationship_type' => 'owner', 'created_by' => $user->id]]);

            $case = $data['surplus_resolution'] === 'use_existing'
                ? SurplusCase::query()->lockForUpdate()->findOrFail($data['surplus_case_id'])
                : $this->createCase($data, $property, $contact, $user);
            Gate::forUser($user)->authorize('view', $case);

            $taxRecord = $this->saveTaxRecord($data, $property, $intake, $user);
            $taskCount = ($data['create_research_tasks'] ?? false) ? $this->createResearchTasks($case, $user) : 0;

            $extraction = $intake->extraction_json ?? [];
            $extraction['approved_values'] = collect($data)->only([
                'parcel_id', 'county', 'address', 'city', 'state', 'postal_code', 'property_type', 'acreage', 'legal_description',
                'first_name', 'last_name', 'mailing_address_line_1', 'mailing_address_line_2', 'mailing_city',
                'mailing_state_province', 'mailing_postal_code', 'mailing_country', 'tax_year', 'market_value',
                'assessed_value', 'taxable_value', 'prior_year_final_tax', 'proposed_tax', 'no_budget_change_tax',
                'non_ad_valorem_assessments', 'assessment_classification', 'tax_deed_number', 'foreclosure_case_number',
                'certificate_number', 'surplus_amount', 'sale_date', 'claim_deadline',
            ])->map(fn (mixed $value): array => ['value' => $value, 'verification_status' => 'user_confirmed'])->all();
            $extraction['approved_by'] = $user->id;
            $extraction['approved_at'] = now()->toIso8601String();
            $intake->update([
                'property_id' => $property->id, 'contact_id' => $contact->id, 'surplus_case_id' => $case->id,
                'status' => 'attached', 'extraction_json' => $extraction, 'attached_at' => now(), 'expires_at' => null,
            ]);
            $conversation->update([
                'status' => 'completed', 'last_message_at' => now(),
                'result_json' => ['property_id' => $property->id, 'contact_id' => $contact->id, 'surplus_case_id' => $case->id, 'property_tax_record_id' => $taxRecord?->id],
            ]);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => "Completed successfully: linked the property and owner, created or linked the Surplus case, stored the tax history separately, attached the source privately, and created {$taskCount} research tasks. No acquisition cost or surplus amount was invented.",
                'metadata_json' => ['property_id' => $property->id, 'contact_id' => $contact->id, 'surplus_case_id' => $case->id],
            ]);
            DB::table('ai_audit_logs')->insert([
                'conversation_id' => $conversation->id, 'user_id' => $user->id,
                'event' => 'surplus_intake_executed', 'metadata_json' => json_encode([
                    'property_id' => $property->id, 'contact_id' => $contact->id,
                    'surplus_case_id' => $case->id, 'tax_record_id' => $taxRecord?->id,
                    'source_hash' => $intake->sha256, 'research_tasks_created' => $taskCount,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return compact('property', 'contact', 'case', 'taxRecord', 'taskCount');
        });
    }

    private function normalizeValues(SurplusExtractionResult $result): array
    {
        $values = [];
        foreach ($result->fields as $field) {
            if (blank($field['value'] ?? null)) continue;
            $name = $field['field'];
            $value = trim((string) $field['value']);
            if (in_array($name, [...self::MONEY_FIELDS, 'acreage'], true)) {
                $numeric = preg_replace('/[^0-9.\-]/', '', $value);
                $value = is_numeric($numeric) ? $numeric : null;
            } elseif ($name === 'tax_year') {
                $value = preg_match('/^\d{4}$/', $value) ? (int) $value : null;
            } elseif ($name === 'state') {
                $value = preg_match('/^[A-Za-z]{2}$/', $value) ? strtoupper($value) : null;
            } elseif ($name === 'property_type') {
                $value = collect(PropertyType::cases())->first(fn ($case) => $case->value === strtolower($value))?->value;
            }
            if ($value !== null && $value !== '') $values[$name] = $value;
        }

        return $values;
    }

    private function propertyDuplicates(array $values): array
    {
        $matches = collect();
        if (filled($values['parcel_id'] ?? null) && filled($values['county'] ?? null) && filled($values['state'] ?? null)) {
            $matches = $matches->merge(Property::query()->where('state', $values['state'])
                ->where('normalized_county', $this->propertyNormalizer->county($values['county']))
                ->where('normalized_parcel_id', $this->propertyNormalizer->parcelId($values['parcel_id']))->get()
                ->map(fn (Property $property) => ['id' => $property->id, 'label' => $property->full_address, 'reason' => 'Exact parcel match']));
        }
        if (filled($values['address'] ?? null) && filled($values['city'] ?? null) && filled($values['state'] ?? null)) {
            $normalized = $this->propertyNormalizer->address($values['address'], $values['city'], $values['state'], $values['postal_code'] ?? null);
            $matches = $matches->merge(Property::query()->where('normalized_address', $normalized)->get()
                ->map(fn (Property $property) => ['id' => $property->id, 'label' => $property->full_address, 'reason' => 'Normalized address match']));
        }
        return $matches->unique('id')->values()->all();
    }

    private function contactDuplicates(array $values): array
    {
        if (blank($values['owner_first_name'] ?? null) || blank($values['owner_last_name'] ?? null)) return [];
        return Contact::query()->whereRaw('LOWER(first_name) = ?', [mb_strtolower($values['owner_first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($values['owner_last_name'])])
            ->when($values['mailing_address_line_1'] ?? null, fn ($query, $address) => $query->whereRaw('LOWER(mailing_address_line_1) = ?', [mb_strtolower($address)]))
            ->limit(10)->get()->map(fn (Contact $contact) => ['id' => $contact->id, 'label' => $contact->full_name, 'reason' => 'Owner name and mailing address match'])->all();
    }

    private function surplusDuplicates(array $values): array
    {
        if (blank($values['parcel_id'] ?? null)) return [];
        return SurplusCase::query()->where('normalized_parcel_id', $this->propertyNormalizer->parcelId($values['parcel_id']))
            ->when($values['state'] ?? null, fn ($query, $state) => $query->where('state', $state))
            ->when($values['county'] ?? null, fn ($query, $county) => $query->where('county', $county))
            ->limit(10)->get()->map(fn (SurplusCase $case) => ['id' => $case->id, 'token' => $case->token, 'label' => $case->case_number, 'reason' => 'Parcel already has a Surplus case'])->all();
    }

    private function documentDuplicates(SurplusIntakeFile $intake): array
    {
        return SurplusIntakeFile::query()->where('sha256', $intake->sha256)->where('status', 'attached')->whereKeyNot($intake->id)
            ->with('surplusCase:id,token,case_number')->get()->map(fn (SurplusIntakeFile $file) => [
                'id' => $file->id, 'surplus_case_id' => $file->surplus_case_id,
                'label' => $file->surplusCase?->case_number ?? 'Attached source', 'reason' => 'Exact document hash match',
            ])->all();
    }

    private function createContact(array $data, User $user): Contact
    {
        return Contact::query()->create([
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'mailing_address_line_1' => $data['mailing_address_line_1'] ?? null, 'mailing_address_line_2' => $data['mailing_address_line_2'] ?? null,
            'mailing_city' => $data['mailing_city'] ?? null, 'mailing_state_province' => $data['mailing_state_province'] ?? null,
            'mailing_postal_code' => $data['mailing_postal_code'] ?? null, 'mailing_country' => $data['mailing_country'] ?? null,
            'type' => ContactType::Surplus, 'status' => ContactStatus::New, 'assigned_user_id' => $data['assigned_user_id'] ?? $user->id,
            'notes' => 'Created from an approved VVR AI Surplus document intake.', 'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
    }

    private function createProperty(array $data, Contact $contact, User $user): Property
    {
        $normalizedParcel = $this->propertyNormalizer->parcelId($data['parcel_id'] ?? null);
        $normalizedCounty = $this->propertyNormalizer->county($data['county']);
        $normalizedAddress = $this->propertyNormalizer->address($data['address'], $data['city'], $data['state'], $data['postal_code'] ?? null);
        if ($normalizedParcel && Property::query()->where('state', $data['state'])->where('normalized_county', $normalizedCounty)->where('normalized_parcel_id', $normalizedParcel)->exists()) {
            throw ValidationException::withMessages(['property_resolution' => 'That parcel already exists. Select “Use existing property” before approval.']);
        }
        if (Property::query()->where('normalized_address', $normalizedAddress)->exists()) {
            throw ValidationException::withMessages(['property_resolution' => 'That normalized address already exists. Select “Use existing property” before approval.']);
        }
        $property = Property::query()->create([
            'parcel_id' => $data['parcel_id'] ?? null, 'normalized_parcel_id' => $normalizedParcel,
            'county' => $data['county'], 'normalized_county' => $normalizedCounty,
            'address' => $data['address'], 'city' => $data['city'], 'state' => $data['state'], 'postal_code' => $data['postal_code'] ?? null,
            'normalized_address' => $normalizedAddress, 'property_type' => $data['property_type'], 'status' => PropertyStatus::Research,
            'acreage' => $data['acreage'] ?? null, 'wetlands_status' => WetlandsStatus::Unknown, 'owner_contact_id' => $contact->id,
            'legal_description' => $data['legal_description'] ?? null,
            'research_notes' => 'Created from an approved prior-year ownership/tax document. Surplus eligibility and amount still require verification.',
            'created_by' => $user->id, 'updated_by' => $user->id,
        ]);
        $this->checklistService->initialize($property, $user);
        return $property;
    }

    private function createCase(array $data, Property $property, Contact $contact, User $user): SurplusCase
    {
        return $this->surplusCaseService->create([
            'status' => SurplusCaseStatus::Research->value, 'claimant_contact_id' => $contact->id, 'property_id' => $property->id,
            'assigned_user_id' => $data['assigned_user_id'] ?? $user->id, 'source' => 'Prior-year tax/property notice',
            'state' => $property->state, 'county' => $property->county, 'parcel_id' => $property->parcel_id,
            'tax_deed_number' => $data['tax_deed_number'] ?? null,
            'foreclosure_case_number' => $data['foreclosure_case_number'] ?? null, 'certificate_number' => $data['certificate_number'] ?? null,
            'surplus_amount' => $data['surplus_amount'] ?? null, 'agreed_fee_percentage' => 12,
            'sale_date' => $data['sale_date'] ?? null, 'claim_deadline' => $data['claim_deadline'] ?? null,
            'notes' => 'Ownership and property information came from an approved document extraction. Confirm the surplus amount, auction/foreclosure record, claimant eligibility, and deadline before outreach or filing.',
        ], $user);
    }

    private function saveTaxRecord(array $data, Property $property, SurplusIntakeFile $intake, User $user): ?PropertyTaxRecord
    {
        if (blank($data['tax_year'] ?? null)) return null;
        $record = PropertyTaxRecord::query()->firstOrNew(['property_id' => $property->id, 'tax_year' => $data['tax_year']]);
        $record->fill([
            'source_intake_id' => $intake->id, 'market_value' => $data['market_value'] ?? null,
            'assessed_value' => $data['assessed_value'] ?? null, 'taxable_value' => $data['taxable_value'] ?? null,
            'prior_year_final_tax' => $data['prior_year_final_tax'] ?? null, 'proposed_tax' => $data['proposed_tax'] ?? null,
            'no_budget_change_tax' => $data['no_budget_change_tax'] ?? null,
            'non_ad_valorem_assessments' => $data['non_ad_valorem_assessments'] ?? null,
            'assessment_classification' => $data['assessment_classification'] ?? null,
            'source_document_type' => 'prior_year_tax_notice', 'updated_by' => $user->id,
        ]);
        if (! $record->exists) $record->created_by = $user->id;
        $record->save();
        return $record;
    }

    private function createResearchTasks(SurplusCase $case, User $user): int
    {
        $titles = ['Verify surplus amount', 'Obtain auction or foreclosure records', 'Verify claimant eligibility', 'Confirm claim deadline'];
        $count = 0;
        foreach ($titles as $title) {
            if ($case->tasks()->where('title', $title)->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])->exists()) continue;
            $case->tasks()->create([
                'title' => $title, 'description' => 'Research required after prior-year ownership/tax document intake.',
                'status' => TaskStatus::Pending, 'priority' => TaskPriority::High,
                'assigned_user_id' => $case->assigned_user_id ?? $user->id, 'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $count++;
        }
        return $count;
    }
}
