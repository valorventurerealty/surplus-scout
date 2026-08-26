<?php

namespace App\Services;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\AuctionCounty;
use App\Enums\ContactStatus;
use App\Enums\ContactType;
use App\Enums\PreAuctionAcquisitionStatus;
use App\Enums\PreAuctionEntitlementStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AiConversation;
use App\Models\AiPreAuctionCsvImport;
use App\Models\AiPreAuctionCsvImportRow;
use App\Models\Contact;
use App\Models\PreAuctionAcquisition;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class VvrAiPreAuctionCsvImportService
{
    private const REQUIRED_HEADERS = [
        'firstname', 'lastname', 'address1', 'city', 'state', 'country', 'postcode',
        'listing_type', 'auction_date', 'parcel_number', 'county',
    ];

    private const HEADER_MAP = [
        'firstname' => 'firstname', 'first_name' => 'firstname',
        'lastname' => 'lastname', 'last_name' => 'lastname',
        'address1' => 'address1', 'address_1' => 'address1',
        'city' => 'city', 'state' => 'state', 'country' => 'country',
        'postcode' => 'postcode', 'postal_code' => 'postcode', 'zip' => 'postcode', 'zip_code' => 'postcode',
        'listing_type' => 'listing_type', 'sale_type' => 'listing_type',
        'assessor_market_value' => 'assessor_market_value', 'market_value' => 'assessor_market_value',
        'auction_date' => 'auction_date', 'sale_date' => 'auction_date', 'v' => 'auction_date',
        'parcel_number' => 'parcel_number', 'parcel_id' => 'parcel_number', 'property_id' => 'parcel_number',
        'appraiser_link' => 'appraiser_link', 'property_appraiser_link' => 'appraiser_link',
        'county' => 'county', 'owner_1_name' => 'owner_record_name', 'owner_name' => 'owner_record_name',
        'property_details_link' => 'property_details_link', 'property_detail_link' => 'property_details_link',
    ];

    public function __construct(
        private readonly PropertyNormalizer $propertyNormalizer,
        private readonly PreAuctionAcquisitionService $preAuctionService,
    ) {}

    public function prepare(UploadedFile $upload, string $prompt, User $user, AiConversation $conversation): AiPreAuctionCsvImport
    {
        abort_unless($conversation->user_id === $user->id, 403);
        $content = $upload->getContent();
        if ($content === '' || str_contains($content, "\0")) {
            throw ValidationException::withMessages(['csv_file' => 'The uploaded file is not a readable plain-text CSV.']);
        }

        [$headers, $records, $columnCount, $rawHeaders] = $this->parse($content);
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, array_keys($headers)));
        if ($missing !== []) {
            throw ValidationException::withMessages(['csv_file' => 'Missing required CSV columns: '.implode(', ', $missing).'.']);
        }
        if ($records === []) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV contains no data rows.']);
        }
        $maximum = (int) config('ai.pre_auction_csv_max_rows', 500);
        if (count($records) > $maximum) {
            throw ValidationException::withMessages(['csv_file' => "This CSV has more than {$maximum} data rows. Split it into smaller files."]);
        }

        $token = (string) Str::uuid();
        $path = 'ai-pre-auction-csv-imports/'.$user->id.'/'.$token.'.csv';
        if (! Storage::disk('local')->put($path, $content)) {
            throw ValidationException::withMessages(['csv_file' => 'The CSV could not be saved to private storage.']);
        }

        try {
            return DB::transaction(function () use ($upload, $user, $conversation, $content, $token, $path, $headers, $records, $columnCount, $rawHeaders): AiPreAuctionCsvImport {
                $import = AiPreAuctionCsvImport::query()->create([
                    'token' => $token, 'user_id' => $user->id, 'ai_conversation_id' => $conversation->id,
                    'disk' => 'local', 'path' => $path,
                    'original_name' => Str::limit(basename($upload->getClientOriginalName()), 255, ''),
                    'mime_type' => $upload->getMimeType() ?: 'text/csv', 'size_bytes' => strlen($content),
                    'sha256' => hash('sha256', $content), 'status' => 'ready',
                    'row_count' => count($records),
                    'expires_at' => now()->addHours((int) config('ai.pre_auction_csv_expiration_hours', 24)),
                ]);

                $seenParcels = [];
                $valid = 0;
                foreach ($records as $offset => $record) {
                    $row = $this->mapRow($record, $headers, $rawHeaders, $offset + 2, $columnCount);
                    $parcelKey = implode('|', [strtolower((string) $row['county']), $row['normalized_parcel_id']]);
                    if ($row['normalized_parcel_id'] && isset($seenParcels[$parcelKey])) {
                        $row['errors_json'][] = 'This county and parcel are repeated in CSV row '.$seenParcels[$parcelKey].'.';
                    } elseif ($row['normalized_parcel_id']) {
                        $seenParcels[$parcelKey] = $row['row_number'];
                    }
                    $row['status'] = $row['errors_json'] === [] ? 'ready' : 'invalid';
                    if ($row['status'] === 'ready') $valid++;
                    $import->rows()->create($row);
                }
                $import->update(['valid_row_count' => $valid]);

                DB::table('ai_audit_logs')->insert([
                    'conversation_id' => $conversation->id, 'user_id' => $user->id,
                    'event' => 'pre_auction_csv_plan_created',
                    'metadata_json' => json_encode(['import_id' => $import->id, 'file_hash' => $import->sha256, 'rows' => count($records), 'valid_rows' => $valid], JSON_THROW_ON_ERROR),
                    'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
                ]);

                return $import->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    public function review(AiPreAuctionCsvImport $import, User $user): array
    {
        abort_unless($import->user_id === $user->id && in_array($import->status, ['ready', 'completed'], true), 403);
        Gate::forUser($user)->authorize('viewAny', PreAuctionAcquisition::class);
        $import->load('rows');

        $rows = $import->rows->map(function (AiPreAuctionCsvImportRow $row): array {
            $contact = $row->status === 'ready' ? $this->findContact($row, false) : null;
            $case = $row->status === 'ready' ? $this->findCase($row, false) : null;
            $conflicts = [];
            if ($case?->auction_at && $row->auction_at && ! $case->auction_at->equalTo($row->auction_at)) {
                $conflicts[] = 'Existing auction date: '.$case->auction_at->format('M j, Y g:i A').'; CSV: '.$row->auction_at->format('M j, Y').'. Existing value will be preserved.';
            }

            return ['model' => $row, 'contact_match' => $contact, 'pre_auction_match' => $case, 'conflicts' => $conflicts];
        })->all();

        return [
            'import' => $import, 'rows' => $rows,
            'valid_rows' => collect($rows)->where('model.status', 'ready')->count(),
            'invalid_rows' => collect($rows)->where('model.status', 'invalid')->count(),
            'duplicate_cases' => collect($rows)->filter(fn (array $row): bool => $row['pre_auction_match'] !== null)->count(),
            'duplicate_contacts' => collect($rows)->filter(fn (array $row): bool => $row['contact_match'] !== null)->count(),
            'duplicate_file' => AiPreAuctionCsvImport::query()->where('sha256', $import->sha256)
                ->where('status', 'completed')->whereKeyNot($import->id)->exists(),
        ];
    }

    public function execute(AiPreAuctionCsvImport $import, array $data, User $user, AiConversation $conversation): array
    {
        return DB::transaction(function () use ($import, $data, $user, $conversation): array {
            $import = AiPreAuctionCsvImport::query()->lockForUpdate()->findOrFail($import->id);
            abort_unless($import->user_id === $user->id && $import->ai_conversation_id === $conversation->id, 403);
            if ($import->status === 'completed') return $import->result_json ?? [];
            if ($import->status !== 'ready' || $import->expires_at?->isPast()) {
                throw ValidationException::withMessages(['approval' => 'This PreTax Auctions CSV approval expired. Upload the CSV again.']);
            }

            Gate::forUser($user)->authorize('create', Contact::class);
            Gate::forUser($user)->authorize('create', PreAuctionAcquisition::class);
            $selected = collect($data['selected_rows'])->map(fn ($id) => (int) $id)->unique()->values();
            $rows = AiPreAuctionCsvImportRow::query()->where('import_id', $import->id)
                ->whereIn('id', $selected)->where('status', 'ready')->lockForUpdate()->orderBy('row_number')->get();
            if ($rows->count() !== $selected->count()) {
                throw ValidationException::withMessages(['selected_rows' => 'One or more selected rows are invalid or no longer available.']);
            }

            $createdContacts = [];
            $reusedContacts = [];
            $createdCases = [];
            $updatedCases = [];
            $tasksCreated = 0;
            foreach ($rows as $row) {
                $contact = $this->findContact($row, true);
                if ($contact) {
                    Gate::forUser($user)->authorize('update', $contact);
                    $changes = ['updated_by' => $user->id];
                    foreach ([
                        'company' => $row->owner_record_name,
                        'mailing_address_line_1' => $row->mailing_address_line_1,
                        'mailing_city' => $row->mailing_city,
                        'mailing_state_province' => $row->mailing_state,
                        'mailing_postal_code' => $row->mailing_postal_code,
                        'mailing_country' => $row->mailing_country,
                    ] as $field => $value) {
                        if (blank($contact->{$field}) && filled($value)) $changes[$field] = $value;
                    }
                    if (count($changes) > 1) $contact->update($changes);
                    $reusedContacts[$contact->id] = ['id' => $contact->id, 'name' => $contact->full_name];
                } else {
                    $contact = Contact::query()->create([
                        'first_name' => $this->nameCase($row->first_name), 'last_name' => $this->nameCase($row->last_name),
                        'company' => $this->nameCase($row->owner_record_name),
                        'mailing_address_line_1' => $row->mailing_address_line_1, 'mailing_city' => $row->mailing_city,
                        'mailing_state_province' => $row->mailing_state, 'mailing_postal_code' => $row->mailing_postal_code,
                        'mailing_country' => $row->mailing_country, 'type' => ContactType::PreTaxAuctions,
                        'status' => ContactStatus::New, 'assigned_user_id' => $user->id,
                        'notes' => 'Created from an approved VVR AI PreTax Auctions CSV import. Mailing address is not the property address.',
                        'created_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                    $createdContacts[$contact->id] = ['id' => $contact->id, 'name' => $contact->full_name];
                }

                $case = $this->findCase($row, true);
                $attributes = [
                    'owner_contact_id' => $contact->id, 'assigned_user_id' => $user->id,
                    'source' => 'VVR AI PreTax Auctions CSV import', 'state' => 'FL', 'county' => $this->countyLabel($row->county),
                    'parcel_id' => $row->parcel_id, 'assessor_market_value' => $row->assessor_market_value,
                    'appraiser_url' => $row->appraiser_url, 'property_details_url' => $row->property_details_url,
                ];
                if ($case) {
                    Gate::forUser($user)->authorize('update', $case);
                    foreach (['owner_contact_id', 'assigned_user_id', 'source', 'assessor_market_value', 'appraiser_url', 'property_details_url'] as $field) {
                        if (filled($case->{$field})) unset($attributes[$field]);
                    }
                    if (! $case->auction_at && $row->auction_at) $attributes['auction_at'] = $row->auction_at;
                    $case = $this->preAuctionService->update($case, $attributes, $user);
                    $updatedCases[] = $this->caseResult($case);
                } else {
                    $case = $this->preAuctionService->create([
                        ...$attributes, 'status' => PreAuctionAcquisitionStatus::Research->value,
                        'entitlement_status' => PreAuctionEntitlementStatus::NotReviewed->value,
                        'auction_at' => $row->auction_at,
                        'notes' => 'Imported from '.$import->original_name.'. Owner mailing address is not a verified property address. Auction URL, property address, tax deed number, and certificate number remain subject to research.',
                    ], $user);
                    $createdCases[] = $this->caseResult($case);
                }

                $taskTitle = 'Verify pre-auction details for parcel '.$row->parcel_id;
                if (! $case->tasks()->where('title', $taskTitle)->exists()) {
                    $case->tasks()->create([
                        'title' => $taskTitle,
                        'description' => 'Verify current ownership, property address, auction time and URL, tax deed number, certificate number, title issues, and the deadline to acquire and record title before auction.',
                        'status' => TaskStatus::Pending, 'priority' => TaskPriority::High,
                        'due_at' => $row->auction_at?->copy()->subDays(14),
                        'assigned_user_id' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                    $tasksCreated++;
                }
                $row->update(['status' => 'executed', 'contact_id' => $contact->id, 'pre_auction_id' => $case->id]);
            }

            $result = [
                'created_contacts' => array_values($createdContacts), 'reused_contacts' => array_values($reusedContacts),
                'created_cases' => $createdCases, 'updated_cases' => $updatedCases,
                'tasks_created' => $tasksCreated, 'selected_rows' => $selected->count(),
            ];
            $import->update(['status' => 'completed', 'result_json' => $result, 'executed_at' => now(), 'expires_at' => null]);
            $conversation->update(['status' => 'completed', 'result_json' => ['pre_auction_csv_import_id' => $import->id, ...$result], 'last_message_at' => now()]);
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => 'Completed successfully: created '.count($createdContacts).' contact(s), reused '.count($reusedContacts).' contact(s), created '.count($createdCases).' PreTax Auction file(s), updated '.count($updatedCases).' existing file(s), and created '.$tasksCreated.' research task(s).',
                'metadata_json' => ['pre_auction_csv_import_id' => $import->id],
            ]);
            DB::table('ai_audit_logs')->insert([
                'conversation_id' => $conversation->id, 'user_id' => $user->id,
                'event' => 'pre_auction_csv_import_executed',
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
            $canonical = self::HEADER_MAP[$normalized] ?? null;
            if ($canonical && ! array_key_exists($canonical, $headers)) $headers[$canonical] = $index;
        }
        $records = [];
        while (($record = fgetcsv($handle)) !== false) {
            if (collect($record)->every(fn ($value): bool => trim((string) $value) === '')) continue;
            $records[] = $record;
        }
        fclose($handle);

        return [$headers, $records, count($rawHeaders), $rawHeaders];
    }

    private function mapRow(array $record, array $headers, array $rawHeaders, int $rowNumber, int $columnCount): array
    {
        $errors = [];
        $warnings = [];
        if (count($record) !== $columnCount) $errors[] = "Expected {$columnCount} columns but found ".count($record).'.';
        $value = fn (string $field): ?string => isset($headers[$field]) ? $this->clean($record[$headers[$field]] ?? null) : null;
        $firstName = $value('firstname');
        $lastName = $value('lastname');
        $parcel = $value('parcel_number');
        $county = $value('county');
        $listingType = $value('listing_type');
        foreach (['First name' => $firstName, 'Last name' => $lastName, 'Mailing address' => $value('address1'), 'Mailing city' => $value('city'), 'Parcel number' => $parcel, 'County' => $county] as $label => $required) {
            if (blank($required)) $errors[] = $label.' is missing.';
        }
        if (! str_contains(strtolower((string) $listingType), 'tax deed')) $errors[] = 'Listing Type must identify a Tax Deed auction.';
        $countyEnum = AuctionCounty::tryFrom(strtolower(trim((string) $county)));
        if (! $countyEnum) $errors[] = 'County is not enabled for the VVR auction calendar.';
        $auctionAt = $this->date($value('auction_date'));
        if (! $auctionAt) $errors[] = 'Auction date is missing or invalid.';
        $auctionHeader = isset($headers['auction_date']) ? strtolower(trim((string) ($rawHeaders[$headers['auction_date']] ?? ''))) : '';
        if ($auctionHeader === 'v') $warnings[] = 'The source column named “v” was mapped to Auction Date because it contains a valid date.';
        $marketValue = $this->money($value('assessor_market_value'));
        if ($marketValue === 0.0) $warnings[] = 'Assessor market value is $0 and should be researched.';
        $warnings[] = 'The mailing address belongs to the contact and will not be used as the property address.';
        $warnings[] = 'No auction URL is present; a research task will be created.';

        return [
            'row_number' => $rowNumber, 'first_name' => Str::limit((string) $firstName, 120, ''),
            'last_name' => Str::limit((string) $lastName, 120, ''), 'owner_record_name' => Str::limit((string) $value('owner_record_name'), 255, ''),
            'mailing_address_line_1' => Str::limit((string) $value('address1'), 255, ''),
            'mailing_city' => Str::limit((string) $value('city'), 120, ''),
            'mailing_state' => Str::limit(strtoupper((string) $value('state')), 120, ''),
            'mailing_country' => Str::limit(strtoupper((string) $value('country')), 2, ''),
            'mailing_postal_code' => Str::limit((string) $value('postcode'), 20, ''),
            'listing_type' => Str::limit((string) $listingType, 80, ''), 'assessor_market_value' => $marketValue,
            'auction_at' => $auctionAt, 'parcel_id' => Str::limit((string) $parcel, 120, ''),
            'normalized_parcel_id' => $this->propertyNormalizer->parcelId($parcel),
            'county' => $countyEnum?->value, 'appraiser_url' => $this->url($value('appraiser_link'), $warnings, 'Appraiser Link'),
            'property_details_url' => $this->url($value('property_details_link'), $warnings, 'Property Details Link'),
            'errors_json' => $errors, 'warnings_json' => $warnings,
        ];
    }

    private function findCase(AiPreAuctionCsvImportRow $row, bool $lock): ?PreAuctionAcquisition
    {
        $query = PreAuctionAcquisition::query()->where('state', 'FL')
            ->where('normalized_parcel_id', $row->normalized_parcel_id)
            ->whereRaw('LOWER(county) = ?', [strtolower($this->countyLabel($row->county))]);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function findContact(AiPreAuctionCsvImportRow $row, bool $lock): ?Contact
    {
        $query = Contact::query()->whereRaw('LOWER(first_name) = ?', [strtolower((string) $row->first_name)])
            ->whereRaw('LOWER(last_name) = ?', [strtolower((string) $row->last_name)]);
        $matches = ($lock ? $query->lockForUpdate() : $query)->get();
        $address = $this->addressKey($row->mailing_address_line_1, $row->mailing_city, $row->mailing_state, $row->mailing_postal_code);
        $exact = $matches->first(fn (Contact $contact): bool => $this->addressKey(
            $contact->mailing_address_line_1, $contact->mailing_city, $contact->mailing_state_province, $contact->mailing_postal_code
        ) === $address);
        if ($exact) return $exact;

        return $matches->count() === 1 && blank($matches->first()->mailing_address_line_1) ? $matches->first() : null;
    }

    private function caseResult(PreAuctionAcquisition $case): array
    {
        return ['id' => $case->id, 'token' => $case->token, 'case_number' => $case->case_number, 'parcel_id' => $case->parcel_id];
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function money(?string $value): ?float
    {
        if (blank($value)) return null;
        $negative = str_contains((string) $value, '(') && str_contains((string) $value, ')');
        $number = preg_replace('/[^0-9.\-]/', '', (string) $value);
        if ($number === '' || ! is_numeric($number)) return null;
        return round((float) $number * ($negative ? -1 : 1), 2);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if (blank($value)) return null;
        foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, (string) $value, config('app.timezone'));
                if ($date !== false && $date->format(ltrim($format, '!')) === $value) return $date->startOfDay();
            } catch (Throwable) {
                // Try the next configured deterministic date format.
            }
        }
        return null;
    }

    private function url(?string $value, array &$warnings, string $label): ?string
    {
        if (blank($value)) return null;
        if (filter_var($value, FILTER_VALIDATE_URL) === false || ! in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $warnings[] = $label.' was invalid and will not be saved.';
            return null;
        }
        return Str::limit($value, 2048, '');
    }

    private function countyLabel(?string $county): string
    {
        return AuctionCounty::tryFrom(strtolower((string) $county))?->label() ?? Str::headline((string) $county);
    }

    private function nameCase(?string $name): ?string
    {
        return filled($name) ? Str::title(Str::lower(trim((string) $name))) : null;
    }

    private function addressKey(?string ...$parts): string
    {
        return collect($parts)->map(fn ($part): string => strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $part)))->implode('|');
    }
}
