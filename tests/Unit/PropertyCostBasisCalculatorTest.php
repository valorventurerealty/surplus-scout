<?php

namespace Tests\Unit;

use App\Services\PropertyCostBasisCalculator;
use PHPUnit\Framework\TestCase;

class PropertyCostBasisCalculatorTest extends TestCase
{
    public function test_it_sums_every_cost_component_in_cents(): void
    {
        $calculator = new PropertyCostBasisCalculator;

        $this->assertSame('17625.25', $calculator->calculate([
            'purchase_price' => '14500.00',
            'taxes' => '875.25',
            'attorney_fees' => '750.00',
            'realtor_fees' => '1200.00',
            'other_costs' => '300.00',
        ]));
    }

    public function test_missing_components_are_zero_but_all_missing_returns_null(): void
    {
        $calculator = new PropertyCostBasisCalculator;

        $this->assertSame('100.00', $calculator->calculate(['purchase_price' => 100]));
        $this->assertNull($calculator->calculate([]));
    }
}
