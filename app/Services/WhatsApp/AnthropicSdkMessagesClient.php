<?php

namespace App\Services\WhatsApp;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Contracts\AnthropicMessagesClient;
use RuntimeException;

/**
 * Real implementation of AnthropicMessagesClient, backed by the official
 * anthropic-ai/sdk package. Request/response shapes verified against the
 * claude-api reference at implementation time (see the Node 4 M4 commit
 * message) rather than assumed.
 */
class AnthropicSdkMessagesClient implements AnthropicMessagesClient
{
    public function complete(string $apiKey, string $model, string $system, array $messages, int $maxTokens): string
    {
        $client = new Client(apiKey: $apiKey);

        try {
            $message = $client->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                system: $system,
                messages: $messages,
            );
        } catch (APIStatusException $exception) {
            throw new RuntimeException("Anthropic API error: {$exception->getMessage()}", previous: $exception);
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return trim($block->text);
            }
        }

        throw new RuntimeException('The AI assistant did not return a text response.');
    }
}
