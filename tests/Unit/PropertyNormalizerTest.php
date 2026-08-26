<?php

namespace Tests\Unit;

use App\Domain\Properties\PropertyNormalizer;
use PHPUnit\Framework\TestCase;

class PropertyNormalizerTest extends TestCase
{
    public function test_it_normalizes_property_identifiers_deterministically(): void
    {
        $normalizer = new PropertyNormalizer;

        $this->assertSame('12123456A', $normalizer->parcelId('12-123-456-A'));
        $this->assertSame('PUTNAM', $normalizer->county(' Putnam County '));
        $this->assertSame(
            '120 BAYBERRY RD PALATKA FL 32177',
            $normalizer->address('120 Bayberry Rd.', 'Palatka', 'fl', '32177'),
        );
        $this->assertNull($normalizer->parcelId('---'));
    }
}
