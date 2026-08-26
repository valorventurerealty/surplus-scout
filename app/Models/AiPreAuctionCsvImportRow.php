<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPreAuctionCsvImportRow extends Model
{
    protected $fillable = [
        'import_id', 'row_number', 'first_name', 'last_name', 'owner_record_name',
        'mailing_address_line_1', 'mailing_city', 'mailing_state', 'mailing_country',
        'mailing_postal_code', 'listing_type', 'assessor_market_value', 'auction_at',
        'parcel_id', 'normalized_parcel_id', 'county', 'appraiser_url', 'property_details_url',
        'status', 'errors_json', 'warnings_json', 'matched_contact_id',
        'matched_pre_auction_id', 'contact_id', 'pre_auction_id',
    ];

    protected function casts(): array
    {
        return [
            'assessor_market_value' => 'decimal:2', 'auction_at' => 'datetime',
            'errors_json' => 'array', 'warnings_json' => 'array',
        ];
    }

    public function import(): BelongsTo { return $this->belongsTo(AiPreAuctionCsvImport::class, 'import_id'); }
    public function matchedContact(): BelongsTo { return $this->belongsTo(Contact::class, 'matched_contact_id'); }
    public function matchedPreAuction(): BelongsTo { return $this->belongsTo(PreAuctionAcquisition::class, 'matched_pre_auction_id'); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function preAuction(): BelongsTo { return $this->belongsTo(PreAuctionAcquisition::class, 'pre_auction_id'); }
}
