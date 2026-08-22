<?php

namespace App\Contracts;

/**
 * Thin seam around the Anthropic Messages API so AiReplyAssistant's
 * business logic (history building, system prompt assembly, guards) can
 * be unit-tested without constructing real SDK response objects - tests
 * bind a fake implementation of this interface instead.
 */
interface AnthropicMessagesClient
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     *
     * @throws \RuntimeException if the API call fails or returns no text
     */
    public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string;
}
