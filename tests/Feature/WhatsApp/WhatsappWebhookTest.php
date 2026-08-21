<?php

use App\Models\Gym;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.whatsapp.app_secret' => 'test-app-secret', 'services.whatsapp.verify_token' => 'test-verify-token']);
});

function signedWhatsappPayload(array $payload): array
{
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    return [$body, $signature];
}

it('completes the verification handshake with the correct token', function (): void {
    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=test-verify-token&hub_challenge=12345')
        ->assertOk()
        ->assertSee('12345');
});

it('rejects the verification handshake with the wrong token', function (): void {
    $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')
        ->assertForbidden();
});

it('rejects a webhook post with an invalid signature', function (): void {
    $this->postJson('/api/webhooks/whatsapp', ['object' => 'whatsapp_business_account'], [
        'X-Hub-Signature-256' => 'sha256=invalid',
    ])->assertForbidden();
});

it('creates a contact, conversation, and message from a valid inbound webhook', function (): void {
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create(['phone_number_id' => '106540352242922']);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => '102290129340398',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => [
                        'display_phone_number' => '15550783881',
                        'phone_number_id' => '106540352242922',
                    ],
                    'contacts' => [[
                        'profile' => ['name' => 'Sheena Nelson'],
                        'wa_id' => '16505551234',
                    ]],
                    'messages' => [[
                        'from' => '16505551234',
                        'id' => 'wamid.TEST123',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'Does it come in another color?'],
                    ]],
                ],
            ]],
        ]],
    ];

    [$body, $signature] = signedWhatsappPayload($payload);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    $contact = WhatsappContact::query()->where('phone', '16505551234')->firstOrFail();

    expect($contact->name)->toBe('Sheena Nelson')
        ->and($contact->gym_id)->toBe($gym->id)
        ->and($contact->last_inbound_at)->not->toBeNull();

    $message = WhatsappMessage::query()->where('meta_message_id', 'wamid.TEST123')->firstOrFail();
    expect($message->body)->toBe('Does it come in another color?')
        ->and($message->direction)->toBe('in');

    expect($contact->conversations()->first()->unread_count)->toBe(1);
});

it('does not double-count a redelivered webhook for the same message id', function (): void {
    $gym = Gym::factory()->create();
    WhatsappPhoneNumber::factory()->for($gym)->create(['phone_number_id' => '106540352242922']);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => '102290129340398',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '106540352242922'],
                    'contacts' => [['profile' => ['name' => 'Sheena'], 'wa_id' => '16505551234']],
                    'messages' => [[
                        'from' => '16505551234',
                        'id' => 'wamid.DUPLICATE',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'Hi'],
                    ]],
                ],
            ]],
        ]],
    ];

    [$body, $signature] = signedWhatsappPayload($payload);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    expect(WhatsappMessage::query()->where('meta_message_id', 'wamid.DUPLICATE')->count())->toBe(1);
});

it('honours an incoming STOP as an automatic opt-out', function (): void {
    $gym = Gym::factory()->create();
    WhatsappPhoneNumber::factory()->for($gym)->create(['phone_number_id' => '106540352242922']);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => '102290129340398',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '106540352242922'],
                    'messages' => [[
                        'from' => '16505551234',
                        'id' => 'wamid.STOP1',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => '  Stop  '],
                    ]],
                ],
            ]],
        ]],
    ];

    [$body, $signature] = signedWhatsappPayload($payload);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    $contact = WhatsappContact::query()->where('phone', '16505551234')->firstOrFail();

    expect($contact->opt_in_status)->toBe('opted_out')
        ->and($contact->opted_out_at)->not->toBeNull();
});

it('updates an outbound message status from a delivery status webhook', function (): void {
    $gym = Gym::factory()->create();
    WhatsappPhoneNumber::factory()->for($gym)->create(['phone_number_id' => '106540352242922']);

    $message = WhatsappMessage::factory()->create([
        'gym_id' => $gym->id,
        'direction' => 'out',
        'status' => 'sent',
        'meta_message_id' => 'wamid.OUT1',
    ]);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => '102290129340398',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '106540352242922'],
                    'statuses' => [[
                        'id' => 'wamid.OUT1',
                        'status' => 'delivered',
                        'timestamp' => (string) now()->timestamp,
                        'recipient_id' => '16505551234',
                    ]],
                ],
            ]],
        ]],
    ];

    [$body, $signature] = signedWhatsappPayload($payload);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    expect($message->fresh()->status)->toBe('delivered');
});
