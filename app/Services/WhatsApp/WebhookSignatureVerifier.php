<?php

namespace App\Services\WhatsApp;

/**
 * Verifies Meta's `X-Hub-Signature-256` header on incoming webhook
 * requests: HMAC-SHA256 of the raw request body, keyed by the app secret,
 * formatted as "sha256={hex}". See:
 * developers.facebook.com/docs/graph-api/webhooks/getting-started
 */
class WebhookSignatureVerifier
{
    public static function verify(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) config('services.whatsapp.app_secret');

        if ($secret === '' || blank($signatureHeader) || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        $provided = substr($signatureHeader, strlen('sha256='));

        return hash_equals($expected, $provided);
    }
}
