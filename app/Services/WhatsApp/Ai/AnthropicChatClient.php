<?php

namespace App\Services\WhatsApp\Ai;

use App\Contracts\AiChatClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Direct HTTP client for Anthropic's Messages API — no SDK dependency.
 *
 * Deliberately not using anthropic-ai/sdk: that package caused a real
 * production-readiness bug (composer.json required it, composer.lock
 * never picked it up, so a plain `composer install` silently skipped it
 * and the AI reply feature would have thrown "Class not found" on
 * first use). A plain call to a stable, versioned REST endpoint removes
 * that entire failure class and keeps this client structurally
 * identical to the other providers in this directory — see
 * OpenAiCompatibleChatClient and AiChatClientFactory.
 */
class AnthropicChatClient implements AiChatClient
{
    private const API_VERSION = '2023-06-01';

    public function __construct(private readonly string $baseUrl = 'https://api.anthropic.com/v1') {}

    public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->acceptJson()
                ->timeout(30)
                ->baseUrl(rtrim($this->baseUrl, '/'))
                ->post('/messages', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => $messages,
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException("Anthropic API request failed: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            $message = $response->json('error.message');

            throw new RuntimeException('Anthropic API error: '.(is_string($message) ? $message : "HTTP {$response->status()}"));
        }

        /** @var list<array<string, mixed>> $blocks */
        $blocks = $response->json('content') ?? [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                return trim($block['text']);
            }
        }

        throw new RuntimeException('The AI assistant did not return a text response.');
    }
}
