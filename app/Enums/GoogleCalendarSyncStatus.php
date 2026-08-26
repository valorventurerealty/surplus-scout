<?php

namespace App\Enums;

enum GoogleCalendarSyncStatus: string
{
    case NotConfigured = 'not_configured';
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case DeletionPending = 'deletion_pending';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::NotConfigured => 'Not connected',
            self::Pending => 'Pending',
            self::Synced => 'Synced',
            self::Failed => 'Failed',
            self::DeletionPending => 'Cancellation pending',
            self::Deleted => 'Removed from Google',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Synced => 'emerald',
            self::Pending, self::DeletionPending => 'amber',
            self::Failed => 'rose',
            default => 'slate',
        };
    }
}
