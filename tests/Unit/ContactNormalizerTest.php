<?php

namespace Tests\Unit;

use App\Domain\Contacts\ContactNormalizer;
use PHPUnit\Framework\TestCase;

class ContactNormalizerTest extends TestCase
{
    public function test_it_normalizes_email_and_phone_deterministically(): void
    {
        $normalizer = new ContactNormalizer;

        $this->assertSame('jordan@example.com', $normalizer->email(' Jordan@Example.COM '));
        $this->assertSame('19045550198', $normalizer->phone('+1 (904) 555-0198'));
        $this->assertNull($normalizer->email('  '));
        $this->assertNull($normalizer->phone('none'));
    }
}
