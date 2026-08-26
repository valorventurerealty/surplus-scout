<?php

namespace Tests\Unit;

use App\Services\NegotiationLadderCalculator;
use PHPUnit\Framework\TestCase;

class NegotiationLadderCalculatorTest extends TestCase
{
    public function test_bayberry_ladder_matches_the_supplied_price_model(): void
    {
        $result = (new NegotiationLadderCalculator)->calculate('22000', '14100', '16900');

        $this->assertCount(21, $result['rows']);
        $this->assertSame([
            'percent' => '100.0',
            'asking_price' => '22000.00',
            'profit' => '7900.00',
            'vvr_split' => '1580.00',
            'investor_one_split' => '3160.00',
            'investor_two_split' => '3160.00',
        ], array_diff_key($result['rows'][0], ['is_closest' => true]));
        $this->assertSame('21450.00', $result['rows'][1]['asking_price']);
        $this->assertSame('97.5', $result['rows'][1]['percent']);
        $this->assertSame('11000.00', $result['rows'][20]['asking_price']);
        $this->assertSame('-3100.00', $result['rows'][20]['profit']);
    }

    public function test_buyer_offer_is_positioned_and_closest_ladder_row_is_marked(): void
    {
        $result = (new NegotiationLadderCalculator)->calculate('22000', '14100', '16900', '77.5');

        $this->assertSame('16900.00', $result['offer']['amount']);
        $this->assertSame('76.8', $result['offer']['percent_of_ask']);
        $this->assertSame('2800.00', $result['offer']['profit']);
        $this->assertSame('560.00', $result['offer']['vvr_split']);
        $this->assertSame('1120.00', $result['offer']['investor_one_split']);
        $this->assertSame('1120.00', $result['offer']['investor_two_split']);
        $this->assertSame('17050.00', $result['counter_offer']['amount']);
        $this->assertSame('77.5', $result['counter_offer']['percent_of_ask']);
        $this->assertSame('2950.00', $result['counter_offer']['profit']);
        $this->assertSame('590.00', $result['counter_offer']['vvr_split']);
        $this->assertSame('1180.00', $result['counter_offer']['investor_one_split']);
        $this->assertSame('1180.00', $result['counter_offer']['investor_two_split']);
        $this->assertTrue($result['rows'][9]['is_closest']);
        $this->assertTrue($result['rows'][9]['is_counter']);
        $this->assertSame('77.5', $result['rows'][9]['percent']);
    }

    public function test_selected_percentage_controls_the_counter_offer(): void
    {
        $result = (new NegotiationLadderCalculator)->calculate('22000', '14100', '17050', '80.0');

        $this->assertSame('17600.00', $result['counter_offer']['amount']);
        $this->assertSame('80.0', $result['counter_offer']['percent_of_ask']);
        $this->assertTrue($result['rows'][8]['is_counter']);
    }

    public function test_counter_offer_is_absent_until_a_percentage_is_selected(): void
    {
        $result = (new NegotiationLadderCalculator)->calculate('22000', '14100', '23000');

        $this->assertNull($result['counter_offer']);
    }

    public function test_below_break_even_rows_retain_signed_projected_splits(): void
    {
        $result = (new NegotiationLadderCalculator)->calculate('22000', '14100');
        $row = null;
        foreach ($result['rows'] as $candidate) {
            if ($candidate['percent'] === '62.5') {
                $row = $candidate;
                break;
            }
        }

        $this->assertNotNull($row);
        $this->assertSame('-350.00', $row['profit']);
        $this->assertSame('-70.00', $row['vvr_split']);
        $this->assertSame('-140.00', $row['investor_one_split']);
        $this->assertSame('-140.00', $row['investor_two_split']);
    }
}
