<?php

namespace App\Providers\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Chat client that speaks the OpenAI Chat Completions protocol.
 *
 * The base URL is configurable so the same implementation serves OpenAI,
 * OpenRouter, Azure, Ollama-compatible endpoints, or a self-hosted proxy —
 * any server that accepts the documented request/response shape.
 */
class OpenAICompatibleChatClient implements ChatClient
{
    public function __construct(
        private string $apiKey,
        private string $baseUrl = 'https://api.openai.com/v1',
        private ?string $model = null,
        private float $temperature = 0.2,
        private int $maxTokens = 2048,
        private int $timeout = 30,
    ) {}

    public function chat(array $messages, array $tools, ?string $model): array
    {
        $payload = [
            'model' => $model ?: $this->model ?: 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];

        if ($tools !== []) {
            // Providers require a JSON array here; a keyed PHP array would
            // serialise as an object and be rejected with "tools must be an array".
            $payload['tools'] = array_values($tools);
            $payload['tool_choice'] = 'auto';
        }

        $attempts = 0;
        $lastError = null;
        $lastStatus = 0;

        while ($attempts < 3) {
            $attempts++;

            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                    ->timeout($this->timeout)
                    ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);
            } catch (ConnectionException $e) {
                $lastError = 'Connection failed: '.$e->getMessage();
                $lastStatus = 504;

                if ($attempts < 3) {
                    usleep($attempts === 1 ? 250000 : 1000000);

                    continue;
                }

                return ['error' => 'timeout', 'status' => 504, 'message' => mb_substr($lastError, 0, 400)];
            }

            if ($response->successful()) {
                $json = (array) $response->json();
                $choice = $json['choices'][0] ?? null;
                $message = (array) ($choice['message'] ?? []);
                $toolCalls = [];

                foreach ((array) ($message['tool_calls'] ?? []) as $call) {
                    $args = json_decode((string) ($call['function']['arguments'] ?? '{}'), true);
                    $toolCalls[] = [
                        'id' => (string) ($call['id'] ?? ''),
                        'type' => 'function',
                        'function' => [
                            'name' => (string) ($call['function']['name'] ?? ''),
                            'arguments' => (string) ($call['function']['arguments'] ?? '{}'),
                        ],
                    ];
                }

                $content = (string) ($message['content'] ?? '');
                $toolCalls = count($toolCalls) > 0 ? $toolCalls : null;

                if ($toolCalls !== null) {
                    return [
                        'role' => 'assistant',
                        'content' => $content !== '' ? $content : null,
                        'tool_calls' => $toolCalls,
                        'finish_reason' => $choice['finish_reason'] ?? 'tool_calls',
                    ];
                }

                return [
                    'role' => 'assistant',
                    'content' => $content,
                    'finish_reason' => $choice['finish_reason'] ?? 'stop',
                ];
            }

            $status = $response->status();
            $lastStatus = $status;
            $lastError = $this->errorMessage((string) $response->body());

            // Retry only on rate-limit / server errors.
            if (! in_array($status, [429, 500, 502, 503, 504], true) || $attempts >= 3) {
                return [
                    'error' => 'chat_failed',
                    'status' => $status,
                    'message' => $lastError,
                ];
            }

            usleep($attempts === 1 ? 250000 : 1000000);
        }

        return [
            'error' => 'chat_failed',
            'status' => $lastStatus ?: 502,
            'message' => $lastError ?? 'AI provider request failed.',
        ];
    }

    /**
     * Providers return machine-readable errors such as
     * {"error":{"message":"\"tools\" must be an array"}}. Surface that sentence
     * instead of the whole JSON envelope so the admin can act on it.
     */
    private function errorMessage(string $body): string
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            $error = $decoded['error'] ?? null;
            $candidate = is_array($error) ? ($error['message'] ?? null) : $error;
            $candidate ??= $decoded['message'] ?? null;

            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return $body;
    }
}
