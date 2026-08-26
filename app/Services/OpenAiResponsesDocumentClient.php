<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenAiResponsesDocumentClient
{
    public function extract(
        string $disk,
        string $path,
        string $mimeType,
        string $originalName,
        string $schemaName,
        array $schema,
        string $systemPrompt,
        string $userPrompt,
    ): array {
        $apiKey = trim((string) config('ai.api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('openai_not_configured: Add OPENAI_API_KEY to the private .env file.');
        }

        $bytes = Storage::disk($disk)->get($path);
        if ($bytes === null) {
            throw new RuntimeException('missing_file: The uploaded file could not be read from private storage.');
        }

        $dataUrl = 'data:'.$mimeType.';base64,'.base64_encode($bytes);
        $fileContent = str_starts_with($mimeType, 'image/')
            ? ['type' => 'input_image', 'image_url' => $dataUrl, 'detail' => 'high']
            : ['type' => 'input_file', 'filename' => $originalName, 'file_data' => $dataUrl];

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout((int) config('ai.timeout', 90))
            ->retry((int) config('ai.max_retries', 2), 750, fn ($exception) => $exception instanceof ConnectionException, throw: false)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('ai.extraction_model'),
                'store' => false,
                'max_output_tokens' => 4000,
                'input' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => [$fileContent, ['type' => 'input_text', 'text' => $userPrompt]]],
                ],
                'text' => ['format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ]],
            ]);

        if ($response->failed()) {
            $code = $response->json('error.code') ?: 'openai_http_'.$response->status();
            $message = $response->status() === 401
                ? 'The OpenAI API key was rejected.'
                : ($response->status() === 429 ? 'OpenAI rate limit reached. Please try again shortly.' : 'OpenAI could not extract this document.');

            throw new RuntimeException($code.': '.$message);
        }

        $json = $response->json();
        $data = json_decode($this->outputText($json), true);
        if (! is_array($data)) {
            throw new RuntimeException('invalid_structured_output: OpenAI returned invalid extraction JSON.');
        }

        return [
            'data' => $data,
            'response_id' => $json['id'] ?? null,
            'input_tokens' => data_get($json, 'usage.input_tokens'),
            'output_tokens' => data_get($json, 'usage.output_tokens'),
        ];
    }

    private function outputText(array $response): string
    {
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('model_refusal: The model declined to process this file.');
                }

                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('missing_output: OpenAI returned no extraction text.');
    }
}
