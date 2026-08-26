<?php

namespace App\Services;

use App\Contracts\AiProviderInterface;
use App\Data\AiDocumentInput;
use App\Data\AiStructuredResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Throwable;

class GeminiAiProvider implements AiProviderInterface
{
    public function __construct(private readonly DocumentTextExtractor $textExtractor) {}

    public function isConfigured(): bool
    {
        return filled(config('ai.api_key')) && config('ai.provider') === 'gemini';
    }

    public function generateStructuredFromDocument(
        AiDocumentInput $document,
        array $schema,
        string $systemPrompt,
        string $userPrompt,
    ): AiStructuredResponse {
        if (! $this->isConfigured()) {
            throw new RuntimeException('gemini_not_configured: Add GEMINI_API_KEY to the private .env file.');
        }

        if (! Storage::disk($document->disk)->exists($document->path)) {
            throw new RuntimeException('missing_file: The uploaded file could not be read from private storage.');
        }

        $promptPart = ['text' => $userPrompt."\n\nSource filename: ".$document->originalName];
        if ($this->textExtractor->supports($document)) {
            $parts = [$promptPart, ['text' => "BEGIN UNTRUSTED DOCUMENT DATA\n".$this->textExtractor->extract($document)."\nEND UNTRUSTED DOCUMENT DATA"]];
        } else {
            $parts = [['inlineData' => [
                'mimeType' => $document->mimeType,
                'data' => base64_encode(Storage::disk($document->disk)->get($document->path)),
            ]], $promptPart];
        }

        return $this->request($parts, $schema, $systemPrompt);
    }

    public function generateStructured(array $schema, string $systemPrompt, string $userPrompt): AiStructuredResponse
    {
        return $this->request([['text' => $userPrompt]], $schema, $systemPrompt);
    }

    private function request(array $parts, array $schema, string $systemPrompt): AiStructuredResponse
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('gemini_not_configured: Add GEMINI_API_KEY to the private .env file.');
        }

        $baseUrl = rtrim((string) config('ai.base_url'), '/');
        if (! str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('invalid_ai_endpoint: The Gemini base URL must use HTTPS.');
        }

        $model = rawurlencode((string) config('ai.extraction_model'));
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['x-goog-api-key' => (string) config('ai.api_key')])
                ->timeout((int) config('ai.timeout', 90))
                ->retry((int) config('ai.max_retries', 2), 500, throw: false)
                ->post("{$baseUrl}/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $schema,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('gemini_unavailable: Gemini could not be reached before the request timed out.', previous: $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('gemini_request_failed: The Gemini request could not be completed.', previous: $exception);
        }

        if (! $response->successful()) {
            $message = match ($response->status()) {
                400 => 'Gemini rejected the document or extraction schema.',
                401, 403 => 'The Gemini API key is invalid or is not permitted to use this model.',
                413 => 'The uploaded document is too large for Gemini.',
                429 => 'The Gemini free-tier limit has been reached. Please try again later.',
                default => 'Gemini could not extract this document.',
            };
            throw new RuntimeException('gemini_http_'.$response->status().': '.$message);
        }

        $payload = $response->json();
        $text = data_get($payload, 'candidates.0.content.parts.0.text');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('missing_output: Gemini returned no structured extraction result.');
        }

        try {
            $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('invalid_structured_output: Gemini returned invalid extraction JSON.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException('invalid_structured_output: Gemini did not return a JSON object.');
        }

        return new AiStructuredResponse(
            data: $data,
            responseId: data_get($payload, 'responseId'),
            inputTokens: data_get($payload, 'usageMetadata.promptTokenCount'),
            outputTokens: data_get($payload, 'usageMetadata.candidatesTokenCount'),
        );
    }
}
