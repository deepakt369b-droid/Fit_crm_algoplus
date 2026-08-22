<?php

namespace App\Services\WhatsApp\Ai;

use App\Contracts\AiChatClient;
use App\Contracts\AiChatClientFactory as AiChatClientFactoryContract;
use InvalidArgumentException;

/**
 * Maps a branch's configured provider name to a concrete AiChatClient.
 * "openai", "kimi", and "glm" all share OpenAiCompatibleChatClient
 * since they expose the same request/response shape — only their base
 * URL differs, resolved from config/services.php's ai_providers list
 * unless the branch supplied its own override.
 */
class AiChatClientFactory implements AiChatClientFactoryContract
{
    public function make(string $provider, ?string $baseUrlOverride = null): AiChatClient
    {
        $baseUrl = filled($baseUrlOverride)
            ? $baseUrlOverride
            : (string) config("services.ai_providers.{$provider}.base_url");

        return match ($provider) {
            'anthropic' => new AnthropicChatClient($baseUrl),
            'openai', 'kimi', 'glm' => new OpenAiCompatibleChatClient($baseUrl),
            default => throw new InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }
}
