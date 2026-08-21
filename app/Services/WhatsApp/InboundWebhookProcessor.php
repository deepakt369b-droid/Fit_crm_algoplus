<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappContact;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a verified Meta Cloud API webhook payload into local rows:
 * inbound messages become WhatsappMessage + WhatsappContact +
 * WhatsappConversation records, and status callbacks update the matching
 * outbound message. See the payload shapes at
 * developers.facebook.com/docs/whatsapp/cloud-api/webhooks/payload-examples.
 */
class InboundWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return;
        }

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $this->processValue((array) ($change['value'] ?? []));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function processValue(array $value): void
    {
        $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

        if ($phoneNumberId === '') {
            return;
        }

        $phoneNumber = WhatsappPhoneNumber::query()
            ->where('phone_number_id', $phoneNumberId)
            ->first();

        if ($phoneNumber === null) {
            Log::warning('WhatsApp webhook for unknown phone_number_id', ['phone_number_id' => $phoneNumberId]);

            return;
        }

        $profiles = collect((array) ($value['contacts'] ?? []))
            ->keyBy(fn (array $contact): string => (string) ($contact['wa_id'] ?? ''));

        foreach ((array) ($value['messages'] ?? []) as $message) {
            $this->processInboundMessage($phoneNumber, (array) $message, $profiles);
        }

        foreach ((array) ($value['statuses'] ?? []) as $status) {
            $this->processStatus((array) $status);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $profiles
     */
    private function processInboundMessage(WhatsappPhoneNumber $phoneNumber, array $message, $profiles): void
    {
        $waId = (string) ($message['from'] ?? '');

        if ($waId === '') {
            return;
        }

        $metaMessageId = $message['id'] ?? null;

        if ($metaMessageId !== null && WhatsappMessage::query()->where('meta_message_id', $metaMessageId)->exists()) {
            return; // already processed (Meta may redeliver webhooks)
        }

        $occurredAt = isset($message['timestamp'])
            ? Carbon::createFromTimestamp((int) $message['timestamp'])
            : now();

        $body = (string) ($message['text']['body'] ?? '');
        $type = (string) ($message['type'] ?? 'unknown');

        DB::transaction(function () use ($phoneNumber, $waId, $metaMessageId, $occurredAt, $body, $type, $profiles): void {
            $contact = WhatsappContact::query()
                ->where('gym_id', $phoneNumber->gym_id)
                ->where('phone', $waId)
                ->first();

            if ($contact === null) {
                $contact = WhatsappContact::create([
                    'gym_id' => $phoneNumber->gym_id,
                    'phone' => $waId,
                    'wa_id' => $waId,
                    'name' => (string) ($profiles->get($waId)['profile']['name'] ?? ''),
                    'source' => 'inbound',
                ]);
            }

            $contact->forceFill(['last_inbound_at' => $occurredAt])->save();

            $this->applyOptOutIfRequested($contact, $body);

            $conversation = WhatsappConversation::query()->firstOrCreate(
                ['wa_phone_number_id' => $phoneNumber->id, 'wa_contact_id' => $contact->id],
                ['gym_id' => $contact->gym_id, 'status' => 'open'],
            );

            $conversation->forceFill([
                'gym_id' => $conversation->gym_id ?? $contact->gym_id,
                'status' => 'open',
                'last_message_at' => $occurredAt,
                'last_inbound_at' => $occurredAt,
                'unread_count' => $conversation->unread_count + 1,
            ])->save();

            WhatsappMessage::create([
                'gym_id' => $conversation->gym_id,
                'wa_conversation_id' => $conversation->id,
                'direction' => 'in',
                'type' => $type,
                'meta_message_id' => $metaMessageId,
                'status' => 'delivered',
                'body' => $body,
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    /**
     * Honour an incoming "STOP" (case-insensitive) as an immediate,
     * automatic opt-out — required by Meta's commerce policy and most
     * messaging regulations, not optional.
     */
    private function applyOptOutIfRequested(WhatsappContact $contact, string $body): void
    {
        if (strtolower(trim($body)) !== 'stop') {
            return;
        }

        $contact->forceFill([
            'opt_in_status' => 'opted_out',
            'opted_out_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function processStatus(array $status): void
    {
        $metaMessageId = $status['id'] ?? null;

        if ($metaMessageId === null) {
            return;
        }

        $message = WhatsappMessage::query()->where('meta_message_id', $metaMessageId)->first();

        if ($message === null) {
            return;
        }

        $newStatus = (string) ($status['status'] ?? '');

        if (! in_array($newStatus, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        $errors = (array) ($status['errors'] ?? []);

        $message->forceFill([
            'status' => $newStatus,
            'error_code' => $errors[0]['code'] ?? null,
            'error_message' => $errors[0]['title'] ?? null,
        ])->save();
    }
}
