<?php

namespace App\Models;

use App\Enums\AuctionCounty;
use App\Enums\AuctionEventType;
use App\Enums\CalendarEventSource;
use App\Enums\GoogleCalendarSyncStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'property_id', 'title', 'parcel_number', 'normalized_parcel_number', 'event_type', 'source', 'starts_at', 'ends_at',
        'auction_url', 'property_address', 'county', 'max_bid', 'notes', 'created_by', 'updated_by',
        'google_calendar_connection_id', 'google_calendar_id', 'google_event_id', 'google_event_html_link',
        'google_event_key', 'google_event_etag', 'google_attendees', 'google_organizer_email',
        'google_updated_at', 'google_cancelled_at', 'google_sync_status', 'google_sync_version',
        'google_sync_error', 'google_sync_attempted_at', 'google_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AuctionEventType::class,
            'source' => CalendarEventSource::class,
            'county' => AuctionCounty::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_bid' => 'decimal:2',
            'google_attendees' => 'array',
            'google_updated_at' => 'datetime',
            'google_cancelled_at' => 'datetime',
            'google_sync_status' => GoogleCalendarSyncStatus::class,
            'google_sync_version' => 'integer',
            'google_sync_attempted_at' => 'datetime',
            'google_synced_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function googleCalendarConnection(): BelongsTo
    {
        return $this->belongsTo(GoogleCalendarConnection::class);
    }

    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        return collect([$this->event_type->label(), $this->property_address])
            ->filter()
            ->implode(' — ');
    }

    public function isGoogleManaged(): bool
    {
        return $this->source === CalendarEventSource::Google;
    }
}
