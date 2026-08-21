<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsappWebhook;
use App\Services\WhatsApp\WebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Meta Cloud API webhook endpoint for WhatsApp. Two responsibilities:
 * the one-time GET verification handshake, and the ongoing POST event
 * deliveries (new messages, delivery status updates).
 *
 * Deliberately outside `routes/api.php`'s `auth:sanctum` groups — Meta
 * calls this directly with no user context. Authenticity instead comes
 * from the `X-Hub-Signature-256` HMAC check on every POST.
 */
class WhatsappWebhookController extends Controller
{
    /**
     * Meta's one-time webhook subscription handshake.
     *
     * @unauthenticated
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $expectedToken = (string) config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, (string) $token)) {
            return response((string) $challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Event delivery — verify the signature, then queue processing so
     * Meta gets a fast 200 regardless of how long ingestion takes.
     *
     * @unauthenticated
     */
    public function handle(Request $request): Response
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! WebhookSignatureVerifier::verify($request->getContent(), $signature)) {
            return response('Invalid signature', 403);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        ProcessWhatsappWebhook::dispatch($payload);

        return response('OK', 200);
    }
}
