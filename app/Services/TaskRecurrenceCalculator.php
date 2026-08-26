<?php

namespace App\Services;

use App\Enums\TaskRecurrence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class TaskRecurrenceCalculator
{
    public function next(CarbonInterface $dueAt, TaskRecurrence $frequency, int $interval): CarbonImmutable
    {
        $date = $dueAt->toImmutable();
        $interval = max(1, $interval);

        return match ($frequency) {
            TaskRecurrence::Daily => $date->addDays($interval),
            TaskRecurrence::Weekly => $date->addWeeks($interval),
            TaskRecurrence::Monthly => $date->addMonthsNoOverflow($interval),
        };
    }
}
