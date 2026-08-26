<?php

namespace Tests\Unit;

use App\Services\FinancialSplitCalculator;
use PHPUnit\Framework\TestCase;

class FinancialSplitCalculatorTest extends TestCase
{
    public function test_bayberry_example_splits_7500_profit_exactly(): void
    {
        $result = (new FinancialSplitCalculator)->calculate('14500.00', '22000.00');

        $this->assertSame('7500.00', $result['profit']);
        $this->assertSame('7500.00', $result['distributable_profit']);
        $this->assertSame('1500.00', $result['vvr_amount']);
        $this->assertSame('3000.00', $result['contact_one_amount']);
        $this->assertSame('3000.00', $result['contact_two_amount']);
    }

    public function test_loss_is_recorded_but_not_distributed(): void
    {
        $result = (new FinancialSplitCalculator)->calculate('14500.00', '13000.00');

        $this->assertSame('-1500.00', $result['profit']);
        $this->assertSame('0.00', $result['distributable_profit']);
        $this->assertSame('0.00', $result['vvr_amount']);
        $this->assertSame('0.00', $result['contact_one_amount']);
        $this->assertSame('0.00', $result['contact_two_amount']);
    }

    public function test_missing_amounts_do_not_produce_fake_values(): void
    {
        $result = (new FinancialSplitCalculator)->calculate(null, '22000.00');

        $this->assertNull($result['profit']);
        $this->assertNull($result['vvr_amount']);
    }
}
