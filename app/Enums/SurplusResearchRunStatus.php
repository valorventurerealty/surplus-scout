<?php

namespace App\Enums;

enum SurplusResearchRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithWarnings = 'completed_with_warnings';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued', self::Running => 'Running', self::Completed => 'Completed',
            self::CompletedWithWarnings => 'Completed With Warnings', self::Failed => 'Failed',
        };
    }

    public function active(): bool
    {
        return in_array($this, [self::Queued, self::Running], true);
    }
}
