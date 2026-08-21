<?php

use App\Models\Gym;
use App\Models\WhatsappContact;
use App\Models\WhatsappConversation;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\OutboundMessageSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeConversation(array $contactOverrides = []): WhatsappConversation
{
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();
    $contact = WhatsappContact::factory()->for($gym)->create($contactOverrides);

    return WhatsappConversation::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'wa_contact_id' => $contact->id,
        'status' => 'open',
    ]);
}

it('sends a free-form text message when the contact is within the 24-hour window', function (): void {
    Http::fake([
        '*/messages' => Http::response([
            'messages' => [['id' => 'wamid.SENT1']],
        ], 200),
    ]);

    $conversation = makeConversation(['last_inbound_at' => now()->subHours(2)]);

    $message = app(OutboundMessageSender::class)->sendText($conversation, 'Hello there!');

    expect($message->status)->toBe('sent')
        ->and($message->meta_message_id)->toBe('wamid.SENT1')
        ->and($message->direction)->toBe('out');
});

it('refuses to send free-form text outside the 24-hour window', function (): void {
    $conversation = makeConversation(['last_inbound_at' => now()->subHours(30)]);

    expect(fn () => app(OutboundMessageSender::class)->sendText($conversation, 'Hello!'))
        ->toThrow(RuntimeException::class);
});

it('refuses to send free-form text when the contact has never messaged in', function (): void {
    $conversation = makeConversation(['last_inbound_at' => null]);

    expect(fn () => app(OutboundMessageSender::class)->sendText($conversation, 'Hello!'))
        ->toThrow(RuntimeException::class);
});

it('refuses to send to an opted-out contact even within the window', function (): void {
    $conversation = makeConversation([
        'last_inbound_at' => now()->subHour(),
        'opt_in_status' => 'opted_out',
    ]);

    expect(fn () => app(OutboundMessageSender::class)->sendText($conversation, 'Hello!'))
        ->toThrow(RuntimeException::class);
});

it('sends a template message outside the 24-hour window', function (): void {
    Http::fake([
        '*/messages' => Http::response([
            'messages' => [['id' => 'wamid.TEMPLATE1']],
        ], 200),
    ]);

    $conversation = makeConversation(['last_inbound_at' => now()->subDays(3)]);
    $template = WhatsappTemplate::factory()->create([
        'wa_phone_number_id' => $conversation->wa_phone_number_id,
        'status' => 'approved',
    ]);

    $message = app(OutboundMessageSender::class)->sendTemplate($conversation, $template);

    expect($message->status)->toBe('sent')
        ->and($message->type)->toBe('template')
        ->and($message->template_name)->toBe($template->name);
});

it('marks a message failed with the provider error instead of leaving it stuck at queued', function (): void {
    Http::fake([
        '*/messages' => Http::response([
            'error' => ['code' => 131047, 'message' => 'Re-engagement message'],
        ], 400),
    ]);

    $conversation = makeConversation(['last_inbound_at' => now()->subHour()]);

    $message = app(OutboundMessageSender::class)->sendText($conversation, 'Hello!');

    expect($message->status)->toBe('failed')
        ->and($message->error_code)->toBe('131047')
        ->and($message->error_message)->toBe('Re-engagement message');
});
