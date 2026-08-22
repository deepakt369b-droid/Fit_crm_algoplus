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
    public function __construct(private readonly AutomationTriggerService $triggers) {}

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

        // Trigger events fire AFTER the transaction commits, not from
        // within it: dispatched automation jobs run on a real queue
        // connection in production, potentially picked up by a worker on
        // a separate DB connection before an uncommitted transaction
        // here would be visible to it.
        [$contact, $wasNewContact, $justOptedIn] = DB::transaction(function () use ($phoneNumber, $waId, $metaMessageId, $occurredAt, $body, $type, $profiles): array {
            $contact = WhatsappContact::query()
                ->where('gym_id', $phoneNumber->gym_id)
                ->where('phone', $waId)
                ->first();

            $wasNewContact = $contact === null;

            if ($contact === null) {
                $contact = WhatsappContact::create([
                    'gym_id' => $phoneNumber->gym_id,
                    'phone' => $waId,
                    'wa_id' => $waId,
                    'name' => (string) data_get($profiles->get($waId), 'profile.name', ''),
                    'source' => 'inbound',
                ]);
            }

            $contact->forceFill(['last_inbound_at' => $occurredAt])->save();

            $justOptedIn = $this->applyOptOutOrOptIn($contact, $body);

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

            return [$contact, $wasNewContact, $justOptedIn];
        });

        if ($wasNewContact) {
            $this->triggers->fireEvent('contact_created', $contact, $phoneNumber->id);
        }

        if ($justOptedIn) {
            $this->triggers->fireEvent('opted_in', $contact, $phoneNumber->id);
        }

        if (trim($body) !== '') {
            $this->triggers->fireKeyword($body, $contact, $phoneNumber->id);
        }
    }

    /**
     * Honour "STOP" as an immediate, automatic opt-out (required by
     * Meta's commerce policy and most messaging regulations, not
     * optional) and "START" as the reciprocal opt back in. Returns
     * whether this message just caused an opt-IN, for the opted_in
     * automation trigger.
     */
    private function applyOptOutOrOptIn(WhatsappContact $contact, string $body): bool
    {
        $normalized = strtolower(trim($body));

        if ($normalized === 'stop') {
            $contact->forceFill([
                'opt_in_status' => 'opted_out',
                'opted_out_at' => now(),
            ])->save();

            return false;
        }

        if ($normalized === 'start' && $contact->opt_in_status !== 'opted_in') {
            $contact->forceFill([
                'opt_in_status' => 'opted_in',
                'opted_in_at' => now(),
            ])->save();

            return true;
        }

        return false;
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
