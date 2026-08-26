<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSurplusCsvImportRow extends Model
{
    protected $fillable = [
        'import_id', 'row_number', 'first_name', 'last_name', 'mailing_address_line_1',
        'mailing_city', 'mailing_state', 'mailing_country', 'mailing_postal_code', 'owner_phone', 'owner_email', 'parcel_id',
        'normalized_parcel_id', 'surplus_amount', 'sale_date', 'tax_deed_number', 'certificate_number',
        'related_contacts_json', 'status', 'errors_json', 'warnings_json',
        'matched_contact_id', 'matched_surplus_case_id', 'contact_id', 'surplus_case_id',
    ];

    protected function casts(): array
    {
        return [
            'surplus_amount' => 'decimal:2', 'sale_date' => 'date',
            'related_contacts_json' => 'array', 'errors_json' => 'array', 'warnings_json' => 'array',
        ];
    }

    public function import(): BelongsTo { return $this->belongsTo(AiSurplusCsvImport::class, 'import_id'); }
    public function matchedContact(): BelongsTo { return $this->belongsTo(Contact::class, 'matched_contact_id'); }
    public function matchedSurplusCase(): BelongsTo { return $this->belongsTo(SurplusCase::class, 'matched_surplus_case_id'); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function surplusCase(): BelongsTo { return $this->belongsTo(SurplusCase::class); }
}
