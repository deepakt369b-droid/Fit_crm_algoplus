<?php

namespace App\Jobs;

use App\Services\WhatsApp\InboundWebhookProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes one verified WhatsApp Cloud API webhook payload off the
 * request/response cycle — Meta expects a fast 200 OK and will retry
 * with backoff (and eventually deactivate the webhook) if acknowledgment
 * is slow, so the controller only verifies the signature and queues this.
 */
class ProcessWhatsappWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload) {}

    public function handle(InboundWebhookProcessor $processor): void
    {
        $processor->process($this->payload);
    }
}
