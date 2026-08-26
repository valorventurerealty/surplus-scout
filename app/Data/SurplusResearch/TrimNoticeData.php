<?php

namespace App\Data\SurplusResearch;

final readonly class TrimNoticeData
{
    public function __construct(
        public int $year,
        public string $ownerRaw,
        public ?string $coOwnerRaw,
        public ?string $mailingAddress,
        public ?string $mailingCity,
        public ?string $mailingState,
        public ?string $mailingZip,
        public string $sourceReference,
        public string $fileHash,
        public string $pdfContents,
        public string $extractedText,
        public ?string $warning = null,
    ) {}
}
