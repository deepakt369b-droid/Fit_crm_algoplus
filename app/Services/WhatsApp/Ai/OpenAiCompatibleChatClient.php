<?php

namespace App\Services\WhatsApp\Ai;

use App\Contracts\AiChatClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Chat-completions client for any OpenAI-compatible /chat/completions
 * endpoint. Covers OpenAI itself, Moonshot's Kimi, and Zhipu's GLM,
 * which all implement the same Bearer-auth, {model, messages}-shaped
 * request and the same {choices[0].message.content} response — a
 * provider that ever diverges from this shape gets its own client
 * instead of being force-fit here. The base URL (and therefore which
 * of those three providers this instance talks to) is supplied by
 * AiChatClientFactory from config/services.php's ai_providers list, or
 * from a branch's own override (see WhatsappAiSetting::$base_url).
 */
class OpenAiCompatibleChatClient implements AiChatClient
{
    public function __construct(private readonly string $baseUrl) {}

    public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
    {
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->baseUrl(rtrim($this->baseUrl, '/'))
                ->post('/chat/completions', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ...$messages,
                    ],
                ]);
        } catch (Throwable $exception) {
            throw new RuntimeException("AI provider request failed: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            $message = $response->json('error.message');

            throw new RuntimeException('AI provider API error: '.(is_string($message) ? $message : "HTTP {$response->status()}"));
        }

        $text = $response->json('choices.0.message.content');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('The AI assistant did not return a text response.');
        }

        return trim($text);
    }
}
