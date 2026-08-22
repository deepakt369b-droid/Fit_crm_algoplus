<?php

namespace App\Contracts;

/**
 * Guards against server-side request forgery (SSRF) for any feature
 * that lets an admin configure an outbound webhook URL — currently just
 * the WhatsApp automation "webhook" step (see AutomationStepExecutor).
 * An interface (rather than a concrete class called directly) so tests
 * can bind a permissive fake instead of depending on real DNS
 * resolution, matching this codebase's existing seam pattern for
 * external I/O (see AiChatClient, MetaCloudApiClient's HTTP calls).
 */
interface UrlSafetyChecker
{
    /**
     * Whether it's safe to let the application make an outbound HTTP
     * request to $url — false for anything that isn't a plain http(s)
     * URL resolving only to public, non-reserved IP addresses.
     */
    public function isSafe(string $url): bool;
}
