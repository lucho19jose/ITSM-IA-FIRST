<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeClient
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('ai.api_key');
        $this->model = config('ai.model');
        $this->baseUrl = config('ai.base_url', 'https://openrouter.ai/api/v1');
        $this->maxTokens = config('ai.max_tokens');
    }

    public function sendMessage(string $systemPrompt, string $userMessage, int $maxTokens = null): array
    {
        $startTime = microtime(true);

        $requestTokens = $maxTokens ?? $this->maxTokens;

        $response = $this->requestWithRetry($this->model, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ], $requestTokens, 90);

        $processingTime = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            Log::error('AI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->throwFriendlyError($response->status());
        }

        $data = $response->json();

        $message = $data['choices'][0]['message'] ?? [];
        $content = trim($message['content'] ?? '');

        // Some reasoning models (stepfun, deepseek) put the answer in content
        // but spend tokens on reasoning. If content is empty, check if finish_reason
        // was 'length' — the model ran out of tokens before generating content.
        // In that case, try to extract useful text from reasoning field.
        if (empty($content)) {
            $reasoning = trim($message['reasoning'] ?? '');
            if (!empty($reasoning)) {
                // Try to find JSON in reasoning (some models put it there)
                $jsonMatch = [];
                if (preg_match('/\{[\s\S]*\}/', $reasoning, $jsonMatch)) {
                    $content = $jsonMatch[0];
                } else {
                    $content = $reasoning;
                }
            }
        }

        $finishReason = $data['choices'][0]['finish_reason'] ?? '';
        if (empty($content)) {
            Log::warning('AI returned empty response', [
                'model' => $this->model,
                'finish_reason' => $finishReason,
                'tokens' => $requestTokens,
            ]);
        }

        return [
            'content' => $content,
            'model' => $data['model'] ?? $this->model,
            'tokens_used' => ($data['usage']['prompt_tokens'] ?? 0) + ($data['usage']['completion_tokens'] ?? 0),
            'processing_time_ms' => $processingTime,
        ];
    }

    public function sendFast(string $systemPrompt, string $userMessage, int $maxTokens = null): array
    {
        $startTime = microtime(true);
        $fastModel = config('ai.fast_model', $this->model);
        $requestTokens = $maxTokens ?? $this->maxTokens;

        // Some free models (Gemma) don't support system messages,
        // so we merge the system prompt into the user message
        $combinedMessage = $systemPrompt . "\n\n---\n\n" . $userMessage;

        $response = $this->requestWithRetry($fastModel, [
            ['role' => 'user', 'content' => $combinedMessage],
        ], $requestTokens, 30);

        $processingTime = (int) ((microtime(true) - $startTime) * 1000);

        if (!$response->successful()) {
            Log::error('AI Fast API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $this->throwFriendlyError($response->status());
        }

        $data = $response->json();
        $content = trim($data['choices'][0]['message']['content'] ?? '');

        return [
            'content' => $content,
            'model' => $data['model'] ?? $fastModel,
            'tokens_used' => ($data['usage']['prompt_tokens'] ?? 0) + ($data['usage']['completion_tokens'] ?? 0),
            'processing_time_ms' => $processingTime,
        ];
    }

    /**
     * Send request with retry on 429 (rate limit).
     */
    private function requestWithRetry(string $model, array $messages, int $maxTokens, int $timeout, int $maxRetries = 2): \Illuminate\Http\Client\Response
    {
        $lastResponse = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            if ($attempt > 0) {
                $delay = $attempt * 2; // 2s, 4s
                sleep($delay);
            }

            $lastResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => 'Chuyma ITSM',
            ])->timeout($timeout)->post($this->baseUrl . '/chat/completions', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => $messages,
            ]);

            if ($lastResponse->status() !== 429) {
                return $lastResponse;
            }

            Log::warning('AI rate limited (429), retry ' . ($attempt + 1) . '/' . $maxRetries, [
                'model' => $model,
            ]);
        }

        return $lastResponse;
    }

    private function throwFriendlyError(int $status): void
    {
        $message = match ($status) {
            429 => 'El servicio de IA está temporalmente saturado. Intenta de nuevo en unos segundos.',
            401 => 'Error de autenticación con el servicio de IA. Contacta al administrador.',
            503 => 'El servicio de IA no está disponible en este momento. Intenta más tarde.',
            default => 'Error al comunicarse con la IA (código ' . $status . ')',
        };

        throw new \RuntimeException($message);
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
