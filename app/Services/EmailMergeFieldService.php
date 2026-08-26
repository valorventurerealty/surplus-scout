<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\PreAuctionAcquisition;
use App\Models\Property;
use App\Models\SurplusCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EmailMergeFieldService
{
    public function values(?Model $record, ?Contact $contact, User $sender): array
    {
        $property = match (true) { $record instanceof Property => $record, $record instanceof Deal => $record->property, $record instanceof SurplusCase => $record->property, $record instanceof PreAuctionAcquisition => $record->property, default => null };
        $surplus = $record instanceof SurplusCase ? $record : null;
        $preAuction = $record instanceof PreAuctionAcquisition ? $record : null;
        // Department case identifiers are authoritative for case correspondence.
        // Property and Deal messages continue to use the Property record.
        $parcelId = $surplus
            ? ($surplus->parcel_id ?? '')
            : ($preAuction ? ($preAuction->parcel_id ?? '') : ($property?->parcel_id ?? ''));
        $county = $surplus
            ? ($surplus->county ?? '')
            : ($preAuction ? ($preAuction->county ?? '') : ($property?->county ?? ''));
        return array_filter([
            '{{first_name}}' => $contact?->first_name ?? '', '{{last_name}}' => $contact?->last_name ?? '',
            '{{contact_name}}' => $contact ? trim($contact->first_name.' '.$contact->last_name) : '',
            '{{property_address}}' => $property?->full_address ?? '', '{{parcel_id}}' => $parcelId,
            '{{county}}' => $county,
            '{{surplus_amount}}' => $surplus?->surplus_amount !== null ? '$'.number_format((float) $surplus->surplus_amount, 2) : '',
            '{{case_number}}' => $surplus?->case_number ?? $preAuction?->case_number ?? ($record instanceof Deal ? $record->deal_number : ''),
            '{{sender_name}}' => $sender->name,
        ], fn (mixed $value): bool => $value !== '');
    }

    public function render(string $value, array $replacements): string { return str_replace(array_keys($replacements), array_values($replacements), $value); }
    public function unresolved(string ...$values): array
    {
        preg_match_all('/\{\{[a-z0-9_]+\}\}/i', implode("\n", $values), $matches);
        return array_values(array_unique($matches[0]));
    }
}
