<?php

namespace App\Data;

final readonly class AiDocumentInput
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $mimeType,
        public string $originalName,
    ) {}
}
