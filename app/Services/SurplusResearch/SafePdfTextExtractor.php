<?php

namespace App\Services\SurplusResearch;

use App\Contracts\SurplusResearch\PdfTextExtractorInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

class SafePdfTextExtractor implements PdfTextExtractorInterface
{
    public function extract(string $pdfContents): string
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                return (new \Smalot\PdfParser\Parser())->parseContent($pdfContents)->getText();
            } catch (\Throwable $exception) {
                throw new RuntimeException('The Clerk PDF could not be converted to text.', previous: $exception);
            }
        }

        $binary = config('surplus_research.osceola.pdf_to_text_binary');
        if (! is_string($binary) || $binary === '' || ! is_file($binary) || ! is_executable($binary)) {
            throw new RuntimeException('PDF extraction is unavailable. Install smalot/pdfparser or configure PDF_TO_TEXT_BINARY.');
        }

        $directory = storage_path('app/private/surplus-research/tmp');
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('The private PDF extraction directory could not be created.');
        }
        $token = bin2hex(random_bytes(16));
        $input = $directory.'/'.$token.'.pdf';
        $output = $directory.'/'.$token.'.txt';

        try {
            if (file_put_contents($input, $pdfContents, LOCK_EX) === false) {
                throw new RuntimeException('The Clerk PDF could not be staged privately.');
            }
            $process = new Process([$binary, '-layout', '-enc', 'UTF-8', $input, $output]);
            $process->setTimeout(45);
            $process->mustRun();
            $text = is_file($output) ? file_get_contents($output) : false;
            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException('PDF extraction returned no text.');
            }
            return $text;
        } catch (\Throwable $exception) {
            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException('The Clerk PDF could not be converted to text.', previous: $exception);
        } finally {
            if (is_file($input)) @unlink($input);
            if (is_file($output)) @unlink($output);
        }
    }
}
