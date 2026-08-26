<?php

namespace App\Enums;

enum OutboundEmailStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft', self::Queued => 'Queued', self::Sending => 'Sending',
            self::Sent => 'Sent', self::Failed => 'Failed', self::Cancelled => 'Cancelled',
        };
    }

    public function isEditable(): bool { return $this === self::Draft; }
}
