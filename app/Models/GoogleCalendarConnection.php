<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\GoogleCalendarConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoogleCalendarConnection extends Model
{
    /** @use HasFactory<GoogleCalendarConnectionFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id', 'google_account_email', 'calendar_id', 'calendar_name', 'access_token',
        'refresh_token', 'token_expires_at', 'scopes', 'is_active', 'connected_at',
        'last_synced_at', 'last_error', 'revoked_at', 'inbound_sync_enabled',
        'inbound_sync_started_at', 'inbound_sync_token', 'last_inbound_sync_at',
        'inbound_sync_error',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'revoked_at' => 'datetime',
            'inbound_sync_enabled' => 'boolean',
            'inbound_sync_started_at' => 'datetime',
            'last_inbound_sync_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function auditExcludedAttributes(): array
    {
        return ['access_token', 'refresh_token'];
    }

    public static function active(): ?self
    {
        return self::query()->where('is_active', true)->whereNotNull('refresh_token')->latest('id')->first();
    }
}
