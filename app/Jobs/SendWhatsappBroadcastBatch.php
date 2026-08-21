<?php

namespace App\Jobs;

use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Models\WhatsappConversation;
use App\Services\WhatsApp\OutboundMessageSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Sends one chunk of a broadcast's pending recipients, then re-dispatches
 * itself (with a short delay) for the next chunk until the broadcast is
 * done. Deliberately NOT a single loop over every recipient: pacing
 * chunks like this is what keeps a large broadcast under Meta's
 * per-second throughput limit (error 130429) instead of hammering the
 * API as fast as PHP can loop.
 */
class SendWhatsappBroadcastBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Recipients sent per chunk. Comfortably under Meta's default 80
     * messages/second throughput even with the delay below providing
     * extra headroom.
     */
    private const CHUNK_SIZE = 20;

    /**
     * Pause between chunks, in seconds.
     */
    private const CHUNK_DELAY_SECONDS = 2;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public readonly int $broadcastId) {}

    public function handle(OutboundMessageSender $sender): void
    {
        $broadcast = WhatsappBroadcast::query()->find($this->broadcastId);

        if ($broadcast === null || $broadcast->isFinished()) {
            return;
        }

        if ($broadcast->status === 'draft' || $broadcast->status === 'scheduled') {
            $broadcast->forceFill(['status' => 'sending', 'started_at' => now()])->save();
        }

        $phoneNumber = $broadcast->phoneNumber;
        $remainingCapacity = $phoneNumber->remainingMessagingCapacity();

        if ($remainingCapacity <= 0) {
            // Tier limit reached for today — stop here rather than draw
            // error 131042 from Meta. The recipients already marked
            // 'pending' stay that way; a scheduled retry (or the admin
            // manually resuming) will pick this broadcast back up once
            // the rolling 24h window has room again.
            $broadcast->forceFill(['status' => 'throttled'])->save();

            return;
        }

        $recipients = WhatsappBroadcastRecipient::query()
            ->where('wa_broadcast_id', $broadcast->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(min(self::CHUNK_SIZE, $remainingCapacity))
            ->get();

        if ($recipients->isEmpty()) {
            $broadcast->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

            return;
        }

        foreach ($recipients as $recipient) {
            $this->sendOne($broadcast, $recipient, $sender);
        }

        $stillPending = WhatsappBroadcastRecipient::query()
            ->where('wa_broadcast_id', $broadcast->id)
            ->where('status', 'pending')
            ->exists();

        if ($stillPending) {
            self::dispatch($broadcast->id)->delay(now()->addSeconds(self::CHUNK_DELAY_SECONDS));
        } else {
            $broadcast->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        }
    }

    private function sendOne(WhatsappBroadcast $broadcast, WhatsappBroadcastRecipient $recipient, OutboundMessageSender $sender): void
    {
        $contact = $recipient->contact;

        if ($contact->opt_in_status === 'opted_out') {
            $recipient->forceFill(['status' => 'skipped', 'error_message' => 'Contact opted out'])->save();

            return;
        }

        $conversation = WhatsappConversation::query()->firstOrCreate(
            ['wa_phone_number_id' => $broadcast->wa_phone_number_id, 'wa_contact_id' => $contact->id],
            ['gym_id' => $broadcast->gym_id ?? $contact->gym_id, 'status' => 'open'],
        );

        $components = $this->buildComponents($recipient->variables ?? []);

        try {
            $message = $sender->sendTemplate($conversation, $broadcast->template, $components, $broadcast->created_by);

            $recipient->forceFill([
                'wa_message_id' => $message->id,
                'status' => $message->status === 'failed' ? 'failed' : 'sent',
                'error_message' => $message->status === 'failed' ? $message->error_message : null,
            ])->save();

            $broadcast->increment($message->status === 'failed' ? 'failed_count' : 'sent_count');
        } catch (RuntimeException $exception) {
            $recipient->forceFill(['status' => 'failed', 'error_message' => $exception->getMessage()])->save();
            $broadcast->increment('failed_count');
        }
    }

    /**
     * @param  list<string>  $variables
     * @return list<array<string, mixed>>
     */
    private function buildComponents(array $variables): array
    {
        if ($variables === []) {
            return [];
        }

        return [[
            'type' => 'body',
            'parameters' => array_map(
                fn (string $value): array => ['type' => 'text', 'text' => $value],
                $variables,
            ),
        ]];
    }
}
