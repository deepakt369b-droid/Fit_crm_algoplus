<?php

use App\Models\Gym;
use App\Models\WhatsappAutomation;
use App\Models\WhatsappAutomationRun;
use App\Models\WhatsappContact;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function signedPayloadFor(array $payload): array
{
    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'test-app-secret');

    return [$body, $signature];
}

beforeEach(function (): void {
    config(['services.whatsapp.app_secret' => 'test-app-secret']);
});

it('starts a run for a contact_created automation when a brand new contact messages in', function (): void {
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create(['phone_number_id' => '106540352242922']);

    $automation = WhatsappAutomation::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'name' => 'Welcome new contacts',
        'trigger_type' => 'contact_created',
        'steps' => [['type' => 'add_tag', 'tag_id' => WhatsappTag::factory()->create(['gym_id' => $gym->id])->id]],
        'status' => 'active',
    ]);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'entry-1',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '106540352242922'],
                    'messages' => [[
                        'from' => '16505551234',
                        'id' => 'wamid.NEW1',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'Hi there'],
                    ]],
                ],
            ]],
        ]],
    ];

    [$body, $signature] = signedPayloadFor($payload);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    expect(WhatsappAutomationRun::query()->where('wa_automation_id', $automation->id)->count())->toBe(1);
});

it('does not re-trigger a contact_created automation for a returning contact', function (): void {
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create(['phone_number_id' => '106540352242922']);
    WhatsappContact::factory()->for($gym)->create(['phone' => '16505551234']);

    $automation = WhatsappAutomation::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'name' => 'Welcome new contacts',
        'trigger_type' => 'contact_created',
        'steps' => [],
        'status' => 'active',
    ]);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'entry-1',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '106540352242922'],
                    'messages' => [[
                        'from' => '16505551234',
                        'id' => 'wamid.RETURN1',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'Hi again'],
                    ]],
                ],
            ]],
        ]],
    ];

    [$body, $signature] = signedPayloadFor($payload);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    expect(WhatsappAutomationRun::query()->where('wa_automation_id', $automation->id)->count())->toBe(0);
});

it('fires a keyword_received automation when the message body matches', function (): void {
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create(['phone_number_id' => '106540352242922']);

    $automation = WhatsappAutomation::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'name' => 'Pricing keyword',
        'trigger_type' => 'keyword_received',
        'trigger_config' => ['keyword' => 'pricing'],
        'steps' => [],
        'status' => 'active',
    ]);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'entry-1',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '106540352242922'],
                    'messages' => [[
                        'from' => '16505551234',
                        'id' => 'wamid.KW1',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => '  Pricing  '],
                    ]],
                ],
            ]],
        ]],
    ];

    [$body, $signature] = signedPayloadFor($payload);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    expect(WhatsappAutomationRun::query()->where('wa_automation_id', $automation->id)->count())->toBe(1);
});

it('opts a contact back in on START and fires the opted_in automation', function (): void {
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create(['phone_number_id' => '106540352242922']);
    $contact = WhatsappContact::factory()->for($gym)->create([
        'phone' => '16505551234',
        'opt_in_status' => 'opted_out',
    ]);

    $automation = WhatsappAutomation::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'name' => 'Welcome back',
        'trigger_type' => 'opted_in',
        'steps' => [],
        'status' => 'active',
    ]);

    $payload = [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => 'entry-1',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'metadata' => ['phone_number_id' => '106540352242922'],
                    'messages' => [[
                        'from' => '16505551234',
                        'id' => 'wamid.START1',
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => 'START'],
                    ]],
                ],
            ]],
        ]],
    ];

    [$body, $signature] = signedPayloadFor($payload);

    $this->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X-Hub-Signature-256' => $signature,
    ], $body)->assertOk();

    expect($contact->fresh()->opt_in_status)->toBe('opted_in');
    expect(WhatsappAutomationRun::query()->where('wa_automation_id', $automation->id)->count())->toBe(1);
});
