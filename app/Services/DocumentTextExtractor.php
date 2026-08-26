<?php

namespace App\Services;

use App\Data\AiDocumentInput;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DocumentTextExtractor
{
    public function supports(AiDocumentInput $document): bool
    {
        return in_array(strtolower(pathinfo($document->originalName, PATHINFO_EXTENSION)), ['txt', 'csv', 'docx'], true);
    }

    public function extract(AiDocumentInput $document): string
    {
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        $extension = strtolower(pathinfo($document->originalName, PATHINFO_EXTENSION));
        $text = match ($extension) {
            'txt', 'csv' => Storage::disk($document->disk)->get($document->path),
            'docx' => $this->docx(Storage::disk($document->disk)->path($document->path)),
            default => throw new RuntimeException('unsupported_file_type: This document type cannot be converted to text.'),
        };

        $text = preg_replace('/\x00/u', '', $text) ?? '';
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('empty_document: No readable text was found in the uploaded document.');
        }

        return mb_substr($text, 0, (int) config('ai.max_text_characters', 250000));
    }

    private function docx(string $path): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('missing_zip_extension: DOCX extraction requires the PHP ZIP extension.');
        }

        $archive = new ZipArchive();
        if ($archive->open($path) !== true) {
            throw new RuntimeException('invalid_docx: The DOCX file could not be opened.');
        }

        try {
            $xml = $archive->getFromName('word/document.xml');
        } finally {
            $archive->close();
        }

        if (! is_string($xml)) {
            throw new RuntimeException('invalid_docx: The DOCX document body is missing.');
        }

        $xml = str_replace(['</w:p>', '</w:tr>', '<w:tab/>'], ["\n", "\n", "\t"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
