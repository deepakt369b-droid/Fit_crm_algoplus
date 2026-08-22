<?php

namespace App\Contracts;

/**
 * Thin seam around a single AI provider's chat-completion API so
 * AiReplyAssistant's business logic (history building, system prompt
 * assembly, guards) can be unit-tested without touching a real HTTP
 * client. Implemented once per provider family — see
 * App\Services\WhatsApp\Ai\AiChatClientFactory for how a provider name
 * resolves to a concrete implementation.
 */
interface AiChatClient
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     *
     * @throws \RuntimeException if the API call fails or returns no text
     */
    public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string;
}
