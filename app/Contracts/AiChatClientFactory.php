<?php

namespace App\Contracts;

/**
 * Resolves a branch's configured AI provider name (see
 * WhatsappAiSetting::$provider) to a concrete AiChatClient. A seam of
 * its own — separate from AiChatClient itself — so AiReplyAssistant's
 * tests can bind one fake factory that always returns one fake client,
 * without needing to fake provider-specific construction.
 */
interface AiChatClientFactory
{
    /**
     * @throws \InvalidArgumentException if $provider isn't a known AI provider
     */
    public function make(string $provider, ?string $baseUrlOverride = null): AiChatClient;
}
