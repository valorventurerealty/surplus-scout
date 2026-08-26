<?php

namespace Tests\Unit\SurplusResearch;

use App\Enums\SurplusOwnerType;
use App\Services\SurplusResearch\OwnerClassificationService;
use App\Services\SurplusResearch\OwnerComparisonService;
use App\Services\SurplusResearch\OwnerNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OwnerNameAndClassificationTest extends TestCase
{
    public function test_superficial_name_differences_match_but_suffixes_remain_significant(): void
    {
        $comparison = new OwnerComparisonService(new OwnerNameNormalizer);
        $this->assertTrue($comparison->equivalent('John A. Smith', 'JOHN A SMITH'));
        $this->assertTrue($comparison->equivalent('John Smith & Mary Smith', 'JOHN SMITH AND MARY SMITH'));
        $this->assertFalse($comparison->equivalent('John Smith', 'John Smith Jr.'));
    }

    #[DataProvider('classifications')]
    public function test_owner_classification(string $owner, ?string $coOwner, SurplusOwnerType $expected): void
    {
        $service = new OwnerClassificationService(new OwnerNameNormalizer);
        $this->assertSame($expected, $service->classify($owner, $coOwner));
    }

    public static function classifications(): array
    {
        return [
            ['John A Smith', null, SurplusOwnerType::Individual],
            ['John A Smith', 'Mary J Smith', SurplusOwnerType::MultipleIndividuals],
            ['John Smith & Mary Smith', null, SurplusOwnerType::MultipleIndividuals],
            ['ABC Holdings LLC', null, SurplusOwnerType::Business],
            ['ABC Corporation', null, SurplusOwnerType::Business],
            ['Estate of John Smith', null, SurplusOwnerType::Estate],
            ['Smith Family Trust', null, SurplusOwnerType::Trust],
            ['Osceola County', null, SurplusOwnerType::GovernmentAssociation],
            ['X7', null, SurplusOwnerType::Unknown],
        ];
    }
}
