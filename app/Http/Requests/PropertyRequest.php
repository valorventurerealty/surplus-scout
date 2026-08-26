<?php

namespace App\Http\Requests;

use App\Domain\Properties\PropertyNormalizer;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\WetlandsStatus;
use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class PropertyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [
            'state' => strtoupper(trim((string) $this->input('state'))),
            'parcel_id' => filled($this->input('parcel_id')) ? trim((string) $this->input('parcel_id')) : null,
            'postal_code' => filled($this->input('postal_code')) ? trim((string) $this->input('postal_code')) : null,
        ];

        foreach (['document_drive_url', 'closing_documents_url'] as $field) {
            if ($this->exists($field)) {
                $data[$field] = filled($this->input($field)) ? trim((string) $this->input($field)) : null;
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $financialRule = Rule::prohibitedIf(! $this->user()?->canViewPropertyFinancials());
        $sourceDocumentRule = Rule::prohibitedIf(! $this->user()?->canViewPropertySourceDocuments());

        return [
            'parcel_id' => ['nullable', 'string', 'max:120'],
            'county' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'postal_code' => ['nullable', 'regex:/^\d{5}(?:-\d{4})?$/'],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'status' => ['required', Rule::enum(PropertyStatus::class)],
            'acreage' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'zoning' => ['nullable', 'string', 'max:120'],
            'flood_zone' => ['nullable', 'string', 'max:120'],
            'wetlands_status' => ['required', Rule::enum(WetlandsStatus::class)],
            'road_access' => ['nullable', 'string', 'max:160'],
            'utilities' => ['nullable', 'array'],
            'utilities.electricity' => ['nullable', 'string', 'max:100'],
            'utilities.water' => ['nullable', 'string', 'max:100'],
            'utilities.sewer' => ['nullable', 'string', 'max:100'],
            'utilities.gas' => ['nullable', 'string', 'max:100'],
            'gis_links_text' => ['nullable', 'string', 'max:10000'],
            'document_drive_url' => [$sourceDocumentRule, 'nullable', 'string', 'max:2048', 'url:https'],
            'closing_documents_url' => [$sourceDocumentRule, 'nullable', 'string', 'max:2048', 'url:https'],
            'owner_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
            'purchase_price' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'arv' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'wholesale_price' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'investor_price' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'taxes' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'attorney_fees' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'realtor_fees' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'other_costs' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'all_in_amount' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'expected_sales_price' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'actual_sales_price' => [$financialRule, 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'intake_token' => [Rule::prohibitedIf($this->route('property') !== null), 'nullable', 'uuid', Rule::exists('property_intake_files', 'token')->where(fn ($query) => $query
                ->where('user_id', $this->user()?->id)
                ->where('status', 'ready')
                ->where('expires_at', '>', now()))],
            'approve_extracted_data' => [Rule::requiredIf($this->filled('intake_token')), 'nullable', 'accepted'],
            'legal_description' => ['nullable', 'string', 'max:50000'],
            'research_notes' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $gisLinks = collect(preg_split('/\R/', (string) $this->input('gis_links_text')))
                ->map(fn (string $url) => trim($url))
                ->filter()
                ->values();

            if ($gisLinks->count() > 10) {
                $validator->errors()->add('gis_links_text', 'Add no more than 10 GIS links.');
            }

            foreach ($gisLinks as $url) {
                if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    $validator->errors()->add('gis_links_text', "The GIS link '{$url}' must be a valid HTTP or HTTPS URL.");
                }
            }

            if ($this->filled('parcel_id') && $this->filled('county') && $this->filled('state')) {
                $normalizer = app(PropertyNormalizer::class);
                $query = Property::query()
                    ->where('state', $this->string('state')->toString())
                    ->where('normalized_county', $normalizer->county($this->string('county')->toString()))
                    ->where('normalized_parcel_id', $normalizer->parcelId($this->string('parcel_id')->toString()));

                if ($property = $this->route('property')) {
                    $query->whereKeyNot($property->getKey());
                }

                if ($query->exists()) {
                    $validator->errors()->add('parcel_id', 'A property with this parcel ID already exists in the selected county and state.');
                }
            }

            if ($this->filled('address') && $this->filled('city') && $this->filled('state')) {
                $normalizer ??= app(PropertyNormalizer::class);
                $addressQuery = Property::query()->where('normalized_address', $normalizer->address(
                    $this->string('address')->toString(),
                    $this->string('city')->toString(),
                    $this->string('state')->toString(),
                    $this->input('postal_code'),
                ));

                if ($property = $this->route('property')) {
                    $addressQuery->whereKeyNot($property->getKey());
                }

                if ($addressQuery->exists()) {
                    $validator->errors()->add('address', 'A property with this normalized address already exists.');
                }
            }
        }];
    }
}
