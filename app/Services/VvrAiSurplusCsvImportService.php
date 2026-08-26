<?php

namespace App\Services;

use App\Domain\Properties\PropertyNormalizer;
use App\Domain\Contacts\ContactNormalizer;
use App\Enums\AuctionCounty;
use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Enums\SurplusCaseStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AiConversation;
use App\Models\AiSurplusCsvImport;
use App\Models\AiSurplusCsvImportRow;
use App\Models\Contact;
use App\Models\SurplusCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VvrAiSurplusCsvImportService
{
    private const REQUIRED_HEADERS = [
        'firstname', 'lastname', 'address1', 'city', 'state', 'country', 'postcode', 'parcel_number', 'surplus',
    ];

    private const HEADER_MAP = [
        'firstname' => 'firstname', 'first_name' => 'firstname',
        'lastname' => 'lastname', 'last_name' => 'lastname',
        'address1' => 'address1', 'address_1' => 'address1',
        'city' => 'city', 'state' => 'state', 'country' => 'country',
        'postcode' => 'postcode', 'postal_code' => 'postcode', 'zip' => 'postcode', 'zip_code' => 'postcode',
        'parcel_number' => 'parcel_number', 'property_id' => 'parcel_number', 'property_id_number' => 'parcel_number',
        'surplus' => 'surplus', 'surplus_available' => 'surplus',
        'sale_date' => 'sale_date', 'tax_deed' => 'tax_deed_number', 'tax_deed_number' => 'tax_deed_number',
        'cert' => 'certificate_number', 'cert_number' => 'certificate_number', 'certificate_number' => 'certificate_number',
        'phone_1_number' => 'owner_phone', 'phone' => 'owner_phone',
        'email_1' => 'owner_email', 'email' => 'owner_email',
    ];

    public function __construct(
        private readonly PropertyNormalizer $propertyNormalizer,
        private readonly ContactNormalizer $contactNormalizer,
        private readonly SurplusCaseService $surplusCaseService,
    ) {}

    public function prepare(UploadedFile $upload, string $prompt, User $user, AiConversation $conversation): AiSurplusCsvImport
    {
        abort_unless($conversation->user_id === $user->id, 403);
        $content = $upload->getContent();
        if (str_contains($content, "\0")) {
            throw ValidationException::withMessages(['csv_file' => 'The uploaded file is not a plain UTF-8 CSV.']);
        }

        [$headers, $records, $columnCount] = $this->parse($content);
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, array_keys($headers)));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'csv_file' => 'Missing required CSV columns: '.implode(', ', $missing).'.',
            ]);
        }
        if ($records === []) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV contains no data rows.']);
        }
        $maximum = (int) config('ai.surplus_csv_max_rows', 500);
        if (count($records) > $maximum) {
            throw ValidationException::withMessages(['csv_file' => "This CSV has more than {$maximum} data rows. Split it into smaller files."]);
        }

        $token = (string) Str::uuid();
        $path = 'ai-surplus-csv-imports/'.$user->id.'/'.$token.'.csv';
        if (! Storage::disk('local')->put($path, $content)) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV could not be saved to private storage.']);
        }

        try {
            return DB::transaction(function () use ($upload, $prompt, $user, $conversation, $content, $token, $path, $headers, $records, $columnCount): AiSurplusCsvImport {
                $county = $this->inferCounty($upload->getClientOriginalName(), $prompt);
                $import = AiSurplusCsvImport::query()->create([
                    'token' => $token, 'user_id' => $user->id, 'ai_conversation_id' => $conversation->id,
                    'disk' => 'local', 'path' => $path,
                    'original_name' => Str::limit(basename($upload->getClientOriginalName()), 255, ''),
                    'mime_type' => $upload->getMimeType() ?: 'text/csv', 'size_bytes' => strlen($content),
                    'sha256' => hash('sha256', $content), 'status' => 'ready', 'default_state' => 'FL',
                    'default_county' => $county, 'row_count' => count($records),
                    'expires_at' => now()->addHours((int) config('ai.surplus_intake_expiration_hours', 24)),
                ]);

                $seenParcels = [];
                $valid = 0;
                foreach ($records as $offset => $record) {
                    $row = $this->mapRow($record, $headers, $offset + 2, $columnCount);
                    $parcelKey = $row['normalized_parcel_id'];
                    if ($parcelKey && isset($seenParcels[$parcelKey])) {
                        $row['errors_json'][] = 'This parcel is repeated in CSV row '.$seenParcels[$parcelKey].'.';
                    } elseif ($parcelKey) {
                        $seenParcels[$parcelKey] = $row['row_number'];
                    }
                    $row['status'] = $row['errors_json'] === [] ? 'ready' : 'invalid';
                    if ($row['status'] === 'ready') {
                        $valid++;
                    }
                    $import->rows()->create($row);
                }
                $import->update(['valid_row_count' => $valid]);

                DB::table('ai_audit_logs')->insert([
                    'conversation_id' => $conversation->id, 'user_id' => $user->id,
                    'event' => 'surplus_csv_plan_created',
                    'metadata_json' => json_encode([
                        'import_id' => $import->id, 'file_hash' => $import->sha256,
                        'rows' => count($records), 'valid_rows' => $valid,
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
                ]);

                return $import->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function review(AiSurplusCsvImport $import, User $user): array
    {
        abort_unless($import->user_id === $user->id && in_array($import->status, ['ready', 'completed'], true), 403);
        abort_unless($user->canViewSurplusFinancials() && $user->can('viewAny', SurplusCase::class), 403);
        $import->load('rows');
        $state = $import->default_state ?: 'FL';
        $county = $import->default_county;
        $contactGroups = $import->rows->where('status', 'ready')->groupBy(fn (AiSurplusCsvImportRow $row): string => $this->contactKey($row))
            ->map(fn ($rows): array => [
                'count' => $rows->count(),
                'address_count' => $rows->map(fn (AiSurplusCsvImportRow $row): string => $this->mailingAddressKey($row))->unique()->count(),
            ]);
        $rows = $import->rows->map(function (AiSurplusCsvImportRow $row) use ($state, $county, $contactGroups): array {
            $contact = $row->status === 'ready' ? $this->findContact($row, false) : null;
            $case = $row->status === 'ready' && filled($county) ? $this->findCase($row, $state, $county, false) : null;
            $group = $contactGroups->get($this->contactKey($row), ['count' => 1, 'address_count' => 1]);

            return [
                'model' => $row, 'contact_match' => $contact,
                'surplus_match' => $case,
                'contact_group_count' => $group['count'],
                'mailing_address_conflict' => $group['address_count'] > 1,
                'projected_fee' => round((float) $row->surplus_amount * SurplusCaseService::MAX_FEE_PERCENTAGE / 100, 2),
            ];
        })->all();

        return [
            'import' => $import,
            'rows' => $rows,
            'valid_rows' => collect($rows)->where('model.status', 'ready')->count(),
            'invalid_rows' => collect($rows)->where('model.status', 'invalid')->count(),
            'duplicate_cases' => collect($rows)->filter(fn (array $row): bool => $row['surplus_match'] !== null)->count(),
            'duplicate_file' => AiSurplusCsvImport::query()->where('sha256', $import->sha256)
                ->where('status', 'completed')->whereKeyNot($import->id)->exists(),
        ];
    }

    public function execute(AiSurplusCsvImport $import, array $data, User $user, AiConversation $conversation): array
    {
        return DB::transaction(function () use ($import, $data, $user, $conversation): array {
            $import = AiSurplusCsvImport::query()->lockForUpdate()->findOrFail($import->id);
            abort_unless($import->user_id === $user->id && $import->ai_conversation_id === $conversation->id, 403);
            if ($import->status === 'completed') {
                return $import->result_json ?? [];
            }
            if ($import->status !== 'ready' || $import->expires_at?->isPast()) {
                throw ValidationException::withMessages(['approval' => 'This CSV approval expired. Upload the CSV again.']);
            }

            Gate::forUser($user)->authorize('create', Contact::class);
            Gate::forUser($user)->authorize('create', SurplusCase::class);
            if (! $user->canViewSurplusFinancials()) {
                throw ValidationException::withMessages(['approval' => 'You cannot approve Surplus financial data.']);
            }

            $selected = collect($data['selected_rows'])->map(fn ($id) => (int) $id)->unique()->values();
            $rows = AiSurplusCsvImportRow::query()->where('import_id', $import->id)
                ->whereIn('id', $selected)->where('status', 'ready')->lockForUpdate()->orderBy('row_number')->get();
            if ($rows->count() !== $selected->count()) {
                throw ValidationException::withMessages(['selected_rows' => 'One or more selected rows are invalid or no longer available.']);
            }

            $createdContacts = [];
            $reusedContacts = [];
            $createdCases = [];
            $updatedCases = [];
            $linkedContacts = [];
            $taskCount = 0;
            foreach ($rows as $row) {
                $existingCase = $this->findCase($row, $data['case_state'], $data['county'], true);
                $contact = $this->findContact($row, true);
                if ($contact) {
                    $this->enrichContact($contact, [
                        'phone' => $row->owner_phone, 'email' => $row->owner_email,
                        'mailing_address_line_1' => $row->mailing_address_line_1, 'mailing_city' => $row->mailing_city,
                        'mailing_state_province' => $row->mailing_state, 'mailing_postal_code' => $row->mailing_postal_code,
                        'mailing_country' => $row->mailing_country,
                    ], $user);
                    if (! isset($createdContacts[$contact->id])) {
                        $reusedContacts[$contact->id] = ['id' => $contact->id, 'name' => $contact->full_name];
                    }
                } else {
                    $contact = Contact::query()->create([
                        'first_name' => $row->first_name, 'last_name' => $row->last_name,
                        'mailing_address_line_1' => $row->mailing_address_line_1, 'mailing_city' => $row->mailing_city,
                        'mailing_state_province' => $row->mailing_state, 'mailing_postal_code' => $row->mailing_postal_code,
                        'mailing_country' => $row->mailing_country, 'phone' => $row->owner_phone, 'email' => $row->owner_email,
                        'type' => ContactType::Surplus,
                        'status' => ContactStatus::New, 'assigned_user_id' => $user->id,
                        'notes' => 'Created from an approved VVR AI Surplus CSV import.',
                        'created_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                    $createdContacts[$contact->id] = ['id' => $contact->id, 'name' => $contact->full_name];
                }

                if ($existingCase) {
                    $changes = [
                        'surplus_amount' => $row->surplus_amount,
                        'agreed_fee_percentage' => SurplusCaseService::MAX_FEE_PERCENTAGE,
                    ];
                    foreach (['tax_deed_number', 'certificate_number'] as $field) {
                        if (blank($existingCase->{$field}) && filled($row->{$field})) $changes[$field] = $row->{$field};
                    }
                    if (! $existingCase->sale_date && $row->sale_date) $changes['sale_date'] = $row->sale_date->toDateString();
                    if (! $existingCase->claimant_contact_id) $changes['claimant_contact_id'] = $contact->id;
                    $case = $this->surplusCaseService->update($existingCase, $changes, $user);
                    $updatedCases[] = ['id' => $case->id, 'token' => $case->token, 'case_number' => $case->case_number, 'parcel_id' => $row->parcel_id];
                    $ownerRole = $case->claimant_contact_id === $contact->id ? 'claimant' : 'other';
                    $this->linkContactToCase($case, $contact, $ownerRole, $ownerRole === 'other' ? 'Listed owner in approved skip-trace CSV; existing primary claimant retained.' : null, $user);
                } else {
                    $case = $this->surplusCaseService->create([
                        'status' => SurplusCaseStatus::Research->value, 'claimant_contact_id' => $contact->id,
                        'assigned_user_id' => $user->id, 'source' => 'VVR AI CSV import',
                        'state' => $data['case_state'], 'county' => $data['county'], 'parcel_id' => $row->parcel_id,
                        'tax_deed_number' => $row->tax_deed_number, 'certificate_number' => $row->certificate_number,
                        'sale_date' => $row->sale_date?->toDateString(), 'surplus_amount' => $row->surplus_amount,
                        'agreed_fee_percentage' => SurplusCaseService::MAX_FEE_PERCENTAGE,
                        'notes' => 'Imported from '.$import->original_name.'. Property address and ownership remain subject to research.',
                    ], $user);
                    $createdCases[] = ['id' => $case->id, 'token' => $case->token, 'case_number' => $case->case_number, 'parcel_id' => $row->parcel_id];
                    if (! $case->tasks()->where('title', 'Research property details for parcel '.$row->parcel_id)->exists()) {
                        $case->tasks()->create([
                            'title' => 'Research property details for parcel '.$row->parcel_id,
                            'description' => 'Verify the property address, current ownership, foreclosure details, and claim eligibility before outreach.',
                            'status' => TaskStatus::Pending, 'priority' => TaskPriority::High,
                            'assigned_user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $taskCount++;
                    }
                }

                foreach ($row->related_contacts_json ?? [] as $person) {
                    $relative = $this->findRelatedContact($person, true);
                    if ($relative) {
                        $this->enrichContact($relative, [
                            ...$this->relatedContactAttributes($person),
                            'notes_append' => $this->relatedContactNotes($person, $row, $import),
                        ], $user);
                        if (! isset($createdContacts[$relative->id])) $reusedContacts[$relative->id] = ['id' => $relative->id, 'name' => $relative->full_name];
                    } else {
                        $attributes = $this->relatedContactAttributes($person);
                        $relative = Contact::query()->create([
                            'first_name' => $person['first_name'], 'last_name' => $person['last_name'], ...$attributes,
                            'type' => ContactType::Surplus, 'status' => ContactStatus::New, 'assigned_user_id' => $user->id,
                            'notes' => $this->relatedContactNotes($person, $row, $import),
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $createdContacts[$relative->id] = ['id' => $relative->id, 'name' => $relative->full_name];
                    }
                    $notes = collect([$person['possible_type'] ?? null, isset($person['age']) ? 'Reported age '.$person['age'] : null])->filter()->implode(' · ');
                    if ($this->linkContactToCase($case, $relative, 'relative', $notes ?: null, $user)) {
                        $linkedContacts[] = ['contact_id' => $relative->id, 'case_id' => $case->id, 'role' => 'relative'];
                    }
                }
                $row->update(['status' => 'executed', 'contact_id' => $contact->id, 'surplus_case_id' => $case->id]);
            }

            $result = [
                'created_contacts' => array_values($createdContacts), 'reused_contacts' => array_values($reusedContacts),
                'created_cases' => $createdCases, 'updated_cases' => $updatedCases, 'linked_contacts' => $linkedContacts,
                'tasks_created' => $taskCount, 'selected_rows' => $selected->count(),
            ];
            $import->update(['status' => 'completed', 'default_state' => $data['case_state'],
                'default_county' => $data['county'], 'result_json' => $result, 'executed_at' => now(), 'expires_at' => null]);
            $conversation->update(['status' => 'completed', 'result_json' => ['surplus_csv_import_id' => $import->id, ...$result], 'last_message_at' => now()]);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Completed successfully: created '.count($createdContacts).' contact(s), reused '.count($reusedContacts).' contact(s), created '.count($createdCases).' Surplus case(s), updated '.count($updatedCases).' existing case(s), linked '.count($linkedContacts).' relative contact(s), and created '.$taskCount.' research task(s).',
                'metadata_json' => ['surplus_csv_import_id' => $import->id],
            ]);
            DB::table('ai_audit_logs')->insert([
                'conversation_id' => $conversation->id, 'user_id' => $user->id,
                'event' => 'surplus_csv_import_executed',
                'metadata_json' => json_encode(['import_id' => $import->id, 'source_hash' => $import->sha256, ...$result], JSON_THROW_ON_ERROR),
                'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $result;
        });
    }

    private function parse(string $content): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rawHeaders = fgetcsv($handle);
        if (! is_array($rawHeaders)) {
            fclose($handle);
            throw ValidationException::withMessages(['csv_file' => 'The CSV header row could not be read.']);
        }
        $headers = [];
        foreach ($rawHeaders as $index => $header) {
            $normalized = Str::of((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header))
                ->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            $canonical = $this->canonicalHeader($normalized);
            if ($canonical !== null && ! array_key_exists($canonical, $headers)) {
                $headers[$canonical] = $index;
            }
        }
        $records = [];
        while (($record = fgetcsv($handle)) !== false) {
            if (collect($record)->every(fn ($value): bool => trim((string) $value) === '')) {
                continue;
            }
            $records[] = $record;
        }
        fclose($handle);

        return [$headers, $records, count($rawHeaders)];
    }

    private function mapRow(array $record, array $headers, int $rowNumber, int $columnCount): array
    {
        $value = function (string $name) use ($record, $headers): string {
            $index = $headers[$name] ?? null;
            return $index !== null ? trim((string) ($record[$index] ?? '')) : '';
        };
        $surplusRaw = str_replace([',', '$', ' '], '', $value('surplus'));
        $surplus = is_numeric($surplusRaw) ? round((float) $surplusRaw, 2) : null;
        $stateRaw = $value('state');
        $state = $this->normalizeState($stateRaw);
        $countryRaw = $value('country');
        $country = $this->normalizeCountry($countryRaw);
        $parcel = $value('parcel_number');
        $saleDateRaw = $value('sale_date');
        $saleDate = $this->normalizeDate($saleDateRaw);
        $errors = [];
        if (count($record) !== $columnCount) {
            $errors[] = 'The row has '.count($record).' columns but the header has '.$columnCount.'. Check for an unquoted comma, especially in a currency amount or address.';
        }
        foreach (['firstname' => 'First name', 'lastname' => 'Last name', 'address1' => 'Mailing address', 'city' => 'Mailing city', 'postcode' => 'Postal code', 'parcel_number' => 'Parcel number'] as $field => $label) {
            if ($value($field) === '') $errors[] = "{$label} is required.";
        }
        if (! preg_match('/^[A-Z]{2}$/', $state)) $errors[] = 'State must be a recognized US state name or two-letter code.';
        if ($country === '') $errors[] = 'Country is required.';
        if (mb_strlen($country) > 100) $errors[] = 'Country may not exceed 100 characters.';
        if ($surplus === null || $surplus < 0 || $surplus > 999999999999.99) $errors[] = 'Surplus must be a valid non-negative amount.';
        if ($saleDateRaw !== '' && $saleDate === null) $errors[] = 'Sale Date must be MM/DD/YYYY or YYYY-MM-DD.';

        return [
            'row_number' => $rowNumber,
            'first_name' => Str::limit($value('firstname'), 120, ''), 'last_name' => Str::limit($value('lastname'), 120, ''),
            'mailing_address_line_1' => Str::limit($value('address1'), 255, ''), 'mailing_city' => Str::limit($value('city'), 120, ''),
            'mailing_state' => Str::limit($state ?: strtoupper($stateRaw), 120, ''),
            'mailing_country' => Str::limit($country, 100, ''), 'mailing_postal_code' => Str::limit($value('postcode'), 20, ''),
            'parcel_id' => Str::limit($parcel, 120, ''), 'normalized_parcel_id' => $this->propertyNormalizer->parcelId($parcel),
            'surplus_amount' => $surplus, 'sale_date' => $saleDate,
            'tax_deed_number' => Str::limit($value('tax_deed_number'), 120, ''),
            'certificate_number' => Str::limit($value('certificate_number'), 120, ''),
            'owner_phone' => Str::limit($value('owner_phone'), 40, ''),
            'owner_email' => Str::limit(strtolower($value('owner_email')), 255, ''),
            'related_contacts_json' => $this->mapRelatedContacts($value),
            'errors_json' => $errors, 'warnings_json' => [],
        ];
    }

    private function canonicalHeader(string $normalized): ?string
    {
        if (isset(self::HEADER_MAP[$normalized])) return self::HEADER_MAP[$normalized];
        if (preg_match('/^relative_([1-9][0-9]*)_(first_name|last_name|possible_type|age|mailing_street|mailing_city|mailing_state|mailing_zip_code|phone_[1-5]_(?:number|type)|email_[1-5])$/', $normalized, $matches) === 1) {
            return 'relative_'.$matches[1].'_'.$matches[2];
        }

        return null;
    }

    private function mapRelatedContacts(callable $value): array
    {
        $people = [];
        for ($position = 1; $position <= 10; $position++) {
            $firstName = trim($value("relative_{$position}_first_name"));
            $lastName = trim($value("relative_{$position}_last_name"));
            if ($firstName === '' && $lastName === '') continue;
            $phones = collect(range(1, 5))->map(fn (int $number): array => [
                'number' => trim($value("relative_{$position}_phone_{$number}_number")),
                'type' => trim($value("relative_{$position}_phone_{$number}_type")),
            ])->filter(fn (array $phone): bool => $phone['number'] !== '')->values()->all();
            $emails = collect(range(1, 5))->map(fn (int $number): string => strtolower(trim($value("relative_{$position}_email_{$number}"))))
                ->filter()->unique()->values()->all();
            $people[] = [
                'first_name' => Str::limit($firstName, 120, ''), 'last_name' => Str::limit($lastName, 120, ''),
                'possible_type' => Str::limit(trim($value("relative_{$position}_possible_type")), 120, ''),
                'age' => is_numeric($value("relative_{$position}_age")) ? (int) $value("relative_{$position}_age") : null,
                'mailing_address_line_1' => Str::limit(trim($value("relative_{$position}_mailing_street")), 255, ''),
                'mailing_city' => Str::limit(trim($value("relative_{$position}_mailing_city")), 120, ''),
                'mailing_state' => $this->normalizeState($value("relative_{$position}_mailing_state")),
                'mailing_postal_code' => Str::limit(trim($value("relative_{$position}_mailing_zip_code")), 20, ''),
                'phones' => $phones, 'emails' => $emails,
            ];
        }

        return $people;
    }

    private function normalizeDate(string $value): ?string
    {
        if ($value === '') return null;
        foreach (['m/d/Y', 'n/j/Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeCountry(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return match ($normalized) {
            'US', 'USA', 'U.S.', 'U.S.A.', 'UNITED STATES', 'UNITED STATES OF AMERICA' => 'US',
            'CA', 'CAN', 'CANADA' => 'CA',
            default => Str::limit(trim($value), 100, ''),
        };
    }

    private function normalizeState(string $value): string
    {
        $normalized = strtoupper(trim($value));
        if (preg_match('/^[A-Z]{2}$/', $normalized) === 1) return $normalized;

        return [
            'ALABAMA'=>'AL','ALASKA'=>'AK','ARIZONA'=>'AZ','ARKANSAS'=>'AR','CALIFORNIA'=>'CA','COLORADO'=>'CO',
            'CONNECTICUT'=>'CT','DELAWARE'=>'DE','DISTRICT OF COLUMBIA'=>'DC','FLORIDA'=>'FL','GEORGIA'=>'GA',
            'HAWAII'=>'HI','IDAHO'=>'ID','ILLINOIS'=>'IL','INDIANA'=>'IN','IOWA'=>'IA','KANSAS'=>'KS',
            'KENTUCKY'=>'KY','LOUISIANA'=>'LA','MAINE'=>'ME','MARYLAND'=>'MD','MASSACHUSETTS'=>'MA','MICHIGAN'=>'MI',
            'MINNESOTA'=>'MN','MISSISSIPPI'=>'MS','MISSOURI'=>'MO','MONTANA'=>'MT','NEBRASKA'=>'NE','NEVADA'=>'NV',
            'NEW HAMPSHIRE'=>'NH','NEW JERSEY'=>'NJ','NEW MEXICO'=>'NM','NEW YORK'=>'NY','NORTH CAROLINA'=>'NC',
            'NORTH DAKOTA'=>'ND','OHIO'=>'OH','OKLAHOMA'=>'OK','OREGON'=>'OR','PENNSYLVANIA'=>'PA',
            'RHODE ISLAND'=>'RI','SOUTH CAROLINA'=>'SC','SOUTH DAKOTA'=>'SD','TENNESSEE'=>'TN','TEXAS'=>'TX',
            'UTAH'=>'UT','VERMONT'=>'VT','VIRGINIA'=>'VA','WASHINGTON'=>'WA','WEST VIRGINIA'=>'WV',
            'WISCONSIN'=>'WI','WYOMING'=>'WY',
        ][$normalized] ?? '';
    }

    private function inferCounty(string $fileName, string $prompt): ?string
    {
        foreach ([$fileName, $prompt] as $source) {
            foreach (AuctionCounty::cases() as $county) {
                if (preg_match('/\b'.preg_quote($county->label(), '/').'\s+County\b/i', $source) === 1) {
                    return $county->label();
                }
            }
        }

        return null;
    }

    private function findContact(AiSurplusCsvImportRow $row, bool $lock): ?Contact
    {
        $query = Contact::query()
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($row->first_name)])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($row->last_name)])
            ->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function findRelatedContact(array $person, bool $lock): ?Contact
    {
        $email = $this->contactNormalizer->email(collect($person['emails'] ?? [])->first());
        $phone = $this->contactNormalizer->phone(data_get($person, 'phones.0.number'));
        $query = Contact::query()->where(function ($query) use ($person, $email, $phone): void {
            if ($email) $query->orWhere('normalized_email', $email);
            if ($phone) $query->orWhere('normalized_phone', $phone);
            $query->orWhere(function ($query) use ($person): void {
                $query->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($person['first_name']))])
                    ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($person['last_name']))]);
                if (filled($person['mailing_address_line_1'] ?? null)) {
                    $query->whereRaw('LOWER(mailing_address_line_1) = ?', [mb_strtolower(trim($person['mailing_address_line_1']))]);
                }
            });
        })->orderBy('id');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function relatedContactAttributes(array $person): array
    {
        $email = collect($person['emails'] ?? [])->first(fn ($value): bool => filter_var($value, FILTER_VALIDATE_EMAIL) !== false);

        return [
            'phone' => data_get($person, 'phones.0.number'), 'email' => $email,
            'mailing_address_line_1' => $person['mailing_address_line_1'] ?? null,
            'mailing_city' => $person['mailing_city'] ?? null,
            'mailing_state_province' => $person['mailing_state'] ?? null,
            'mailing_postal_code' => $person['mailing_postal_code'] ?? null,
            'mailing_country' => 'US',
        ];
    }

    private function enrichContact(Contact $contact, array $candidates, User $user): void
    {
        $changes = [];
        foreach (['phone', 'email', 'mailing_address_line_1', 'mailing_city', 'mailing_state_province', 'mailing_postal_code', 'mailing_country'] as $field) {
            if (blank($contact->{$field}) && filled($candidates[$field] ?? null)) $changes[$field] = $candidates[$field];
        }
        $append = trim((string) ($candidates['notes_append'] ?? ''));
        if ($append !== '' && ! str_contains((string) $contact->notes, $append)) {
            $changes['notes'] = collect([$contact->notes, $append])->filter()->implode("\n\n");
        }
        if ($changes !== []) $contact->update([...$changes, 'updated_by' => $user->id]);
    }

    private function relatedContactNotes(array $person, AiSurplusCsvImportRow $row, AiSurplusCsvImport $import): string
    {
        $phones = collect($person['phones'] ?? [])->map(fn (array $phone): string => trim($phone['number'].' '.($phone['type'] ?? '')))->implode(', ');
        $emails = collect($person['emails'] ?? [])->implode(', ');

        return collect([
            'Skip-trace relative for parcel '.$row->parcel_id.'.',
            filled($person['possible_type'] ?? null) ? 'Reported relationship: '.$person['possible_type'].'.' : null,
            isset($person['age']) ? 'Reported age: '.$person['age'].'.' : null,
            $phones !== '' ? 'Additional phones: '.$phones.'.' : null,
            $emails !== '' ? 'Additional emails: '.$emails.'.' : null,
            'Source: approved VVR AI import '.$import->original_name.'.',
        ])->filter()->implode("\n");
    }

    private function linkContactToCase(SurplusCase $case, Contact $contact, string $role, ?string $notes, User $user): bool
    {
        if (! Schema::hasTable('contact_surplus_case')) {
            throw ValidationException::withMessages(['approval' => 'The Surplus multi-contact migration has not been installed.']);
        }
        $existing = DB::table('contact_surplus_case')->where('surplus_case_id', $case->id)->where('contact_id', $contact->id)->first();
        $effectiveRole = $existing?->role === 'claimant' ? 'claimant' : $role;
        DB::table('contact_surplus_case')->updateOrInsert(
            ['surplus_case_id' => $case->id, 'contact_id' => $contact->id],
            ['role' => $effectiveRole, 'relationship_notes' => $notes ?: $existing?->relationship_notes,
                'created_by' => $existing?->created_by ?? $user->id,
                'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()],
        );

        return $existing === null;
    }

    private function contactKey(AiSurplusCsvImportRow $row): string
    {
        return mb_strtolower(trim($row->first_name)).'|'.mb_strtolower(trim($row->last_name));
    }

    private function mailingAddressKey(AiSurplusCsvImportRow $row): string
    {
        return mb_strtolower(implode('|', array_map('trim', [
            $row->mailing_address_line_1, $row->mailing_city, $row->mailing_state,
            $row->mailing_postal_code, $row->mailing_country,
        ])));
    }

    private function findCase(AiSurplusCsvImportRow $row, string $state, string $county, bool $lock): ?SurplusCase
    {
        $normalizedParcel = $this->propertyNormalizer->parcelId($row->parcel_id);
        $query = SurplusCase::query()->with('property:id,normalized_parcel_id')->where(function ($query) use ($row, $normalizedParcel): void {
            if ($normalizedParcel) {
                $query->where('normalized_parcel_id', $normalizedParcel)
                    ->orWhereHas('property', fn ($property) => $property->where('normalized_parcel_id', $normalizedParcel));
            }
            if (filled($row->tax_deed_number)) {
                $method = $normalizedParcel ? 'orWhere' : 'where';
                $query->{$method}('tax_deed_number', $row->tax_deed_number);
            }
        })->orderBy('id');
        $matches = ($lock ? $query->lockForUpdate() : $query)->get();
        if ($matches->isNotEmpty()) {
            $normalizedState = strtoupper(trim($state));
            $normalizedCounty = $this->propertyNormalizer->county($county);

            return $matches->first(fn (SurplusCase $case): bool => strtoupper((string) $case->state) === $normalizedState
                && $this->propertyNormalizer->county((string) $case->county) === $normalizedCounty)
                ?? $matches->first();
        }

        // Some older cases were created before parcel normalization or only have the claimant association.
        // Use that fallback only when it resolves to exactly one plausible case; never guess between cases.
        $owner = $this->findContact($row, $lock);
        if (! $owner) return null;
        $ownerCases = $owner->surplusCases()->orderBy('id');
        $ownerCases = ($lock ? $ownerCases->lockForUpdate() : $ownerCases)->get();
        $plausible = $ownerCases->filter(function (SurplusCase $case) use ($row): bool {
            if (blank($case->normalized_parcel_id)) return true;
            return $case->surplus_amount !== null && abs((float) $case->surplus_amount - (float) $row->surplus_amount) < 0.01;
        });

        return $plausible->count() === 1 ? $plausible->first() : null;
    }
}
