<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Sends an outbound message for a conversation, enforcing Meta's 24-hour
 * customer service window: free-form text only within 24 hours of the
 * contact's last inbound message, a pre-approved template otherwise. This
 * is enforced here (not just in the UI) because it's a hard Meta policy
 * rule, not a preference — a free-form send outside the window is
 * rejected by Meta anyway, just later and less clearly.
 */
class OutboundMessageSender
{
    public function sendText(WhatsappConversation $conversation, string $body, ?int $sentByUserId = null): WhatsappMessage
    {
        $contact = $conversation->contact;

        if (! $contact->isWithinServiceWindow()) {
            throw new RuntimeException(
                'This contact has not messaged in the last 24 hours — send a template message instead of free-form text.'
            );
        }

        if ($contact->opt_in_status === 'opted_out') {
            throw new RuntimeException('This contact has opted out and cannot be messaged.');
        }

        $message = $this->createOutboundRecord($conversation, 'text', $sentByUserId, body: $body);

        $this->send($message, fn (MetaCloudApiClient $client): array => $client->sendText(
            $contact->wa_id ?? $contact->phone,
            $body,
        ), $conversation);

        return $message;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    public function sendTemplate(
        WhatsappConversation $conversation,
        WhatsappTemplate $template,
        array $components = [],
        ?int $sentByUserId = null,
    ): WhatsappMessage {
        $contact = $conversation->contact;

        if ($contact->opt_in_status === 'opted_out') {
            throw new RuntimeException('This contact has opted out and cannot be messaged.');
        }

        $message = $this->createOutboundRecord(
            $conversation,
            'template',
            $sentByUserId,
            templateName: $template->name,
        );

        $this->send($message, fn (MetaCloudApiClient $client): array => $client->sendTemplate(
            $contact->wa_id ?? $contact->phone,
            $template->name,
            $template->language,
            $components,
        ), $conversation);

        return $message;
    }

    /**
     * Calls the Cloud API and applies its result to the message row —
     * including on failure, so a rejected/erroring send is recorded as
     * `failed` with Meta's error rather than left stuck at `queued`.
     *
     * @param  callable(MetaCloudApiClient): array<string, mixed>  $call
     */
    private function send(WhatsappMessage $message, callable $call, WhatsappConversation $conversation): void
    {
        $client = new MetaCloudApiClient($conversation->phoneNumber);

        try {
            $response = $call($client);
            $metaMessageId = $response['messages'][0]['id'] ?? null;

            $message->forceFill([
                'status' => $metaMessageId !== null ? 'sent' : 'failed',
                'meta_message_id' => $metaMessageId,
            ])->save();
        } catch (RequestException $exception) {
            $error = $exception->response?->json('error') ?? [];

            $message->forceFill([
                'status' => 'failed',
                'error_code' => isset($error['code']) ? (string) $error['code'] : null,
                'error_message' => $error['message'] ?? $exception->getMessage(),
            ])->save();
        }
    }

    private function createOutboundRecord(
        WhatsappConversation $conversation,
        string $type,
        ?int $sentByUserId,
        ?string $body = null,
        ?string $templateName = null,
    ): WhatsappMessage {
        $message = WhatsappMessage::create([
            'gym_id' => $conversation->gym_id,
            'wa_conversation_id' => $conversation->id,
            'direction' => 'out',
            'type' => $type,
            'status' => 'queued',
            'body' => $body,
            'template_name' => $templateName,
            'sent_by' => $sentByUserId,
            'occurred_at' => now(),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }
}
