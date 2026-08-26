<?php

namespace Tests\Unit;

use App\Enums\TaskRecurrence;
use App\Services\TaskRecurrenceCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class TaskRecurrenceCalculatorTest extends TestCase
{
    public function test_it_calculates_daily_weekly_and_monthly_occurrences_without_month_overflow(): void
    {
        $calculator = new TaskRecurrenceCalculator;

        $this->assertSame('2026-08-03', $calculator->next(CarbonImmutable::parse('2026-08-01'), TaskRecurrence::Daily, 2)->toDateString());
        $this->assertSame('2026-08-15', $calculator->next(CarbonImmutable::parse('2026-08-01'), TaskRecurrence::Weekly, 2)->toDateString());
        $this->assertSame('2026-02-28', $calculator->next(CarbonImmutable::parse('2026-01-31'), TaskRecurrence::Monthly, 1)->toDateString());
    }
}
