<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappPhoneNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Meta (WhatsApp Business) Cloud API for a single
 * phone number. See developers.facebook.com/docs/whatsapp/cloud-api.
 */
class MetaCloudApiClient
{
    public function __construct(private readonly WhatsappPhoneNumber $phoneNumber) {}

    /**
     * Send a free-form text message. Only permitted within Meta's 24-hour
     * customer service window from the recipient's last inbound message —
     * callers must check WhatsappContact::isWithinServiceWindow() first;
     * this client does not enforce it, since it operates on a raw phone
     * number, not a contact record.
     *
     * @return array<string, mixed>
     */
    public function sendText(string $to, string $body): array
    {
        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    /**
     * Send a pre-approved template message — the only message type Meta
     * allows outside the 24-hour service window.
     *
     * @param  list<array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    public function sendTemplate(string $to, string $templateName, string $language, array $components = []): array
    {
        return $this->post('messages', [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Fetch this number's WABA-approved templates from the Graph API.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchTemplates(): array
    {
        $response = $this->client()->get("/{$this->phoneNumber->waba_id}/message_templates", [
            'limit' => 200,
        ]);

        $response->throw();

        /** @var list<array<string, mixed>> $data */
        $data = $response->json('data') ?? [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->client()
                ->post("/{$this->phoneNumber->phone_number_id}/{$path}", $payload);

            $response->throw();

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];

            return $data;
        } catch (RequestException $exception) {
            Log::warning('WhatsApp Cloud API request failed', [
                'phone_number_id' => $this->phoneNumber->phone_number_id,
                'path' => $path,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
            ]);

            throw $exception;
        }
    }

    private function client(): PendingRequest
    {
        $version = (string) config('services.whatsapp.api_version', 'v21.0');
        $base = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com'), '/');

        return Http::baseUrl("{$base}/{$version}")
            ->withToken((string) $this->phoneNumber->access_token)
            ->acceptJson()
            ->timeout(15);
    }
}
