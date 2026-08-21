<?php

use App\Jobs\SendWhatsappBroadcastBatch;
use App\Models\Gym;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappBroadcastRecipient;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeBroadcastWithRecipients(int $recipientCount, array $phoneNumberOverrides = []): WhatsappBroadcast
{
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create($phoneNumberOverrides);
    $template = WhatsappTemplate::factory()->for($phoneNumber, 'phoneNumber')->create(['status' => 'approved']);

    $broadcast = WhatsappBroadcast::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'wa_template_id' => $template->id,
        'name' => 'Test broadcast',
        'status' => 'scheduled',
    ]);

    for ($i = 0; $i < $recipientCount; $i++) {
        WhatsappBroadcastRecipient::query()->create([
            'wa_broadcast_id' => $broadcast->id,
            'wa_contact_id' => WhatsappContact::factory()->for($gym)->create(['opt_in_status' => 'opted_in'])->id,
            'status' => 'pending',
            'variables' => ['Alex'],
        ]);
    }

    $broadcast->forceFill(['total_recipients' => $recipientCount])->save();

    return $broadcast;
}

it('sends every pending recipient and marks the broadcast completed', function (): void {
    Http::fake([
        '*/messages' => Http::sequence()
            ->push(['messages' => [['id' => 'wamid.B1']], 'contacts' => []], 200)
            ->push(['messages' => [['id' => 'wamid.B2']], 'contacts' => []], 200)
            ->push(['messages' => [['id' => 'wamid.B3']], 'contacts' => []], 200),
    ]);

    $broadcast = makeBroadcastWithRecipients(3);

    (new SendWhatsappBroadcastBatch($broadcast->id))->handle(app(\App\Services\WhatsApp\OutboundMessageSender::class));

    expect($broadcast->fresh())
        ->status->toBe('completed')
        ->sent_count->toBe(3);

    expect(WhatsappBroadcastRecipient::query()->where('status', 'sent')->count())->toBe(3);
    expect(WhatsappMessage::query()->count())->toBe(3);
});

it('stops dispatching once the phone number\'s messaging tier limit is reached', function (): void {
    Http::fake([
        '*/messages' => Http::response(['messages' => [['id' => 'wamid.X']]], 200),
    ]);

    $broadcast = makeBroadcastWithRecipients(5, ['messaging_tier_limit' => 2]);

    // The test queue connection is 'sync' (phpunit.xml), so the job's own
    // self-redispatch for the next chunk runs inline here too: chunk 1
    // sends 2 (exhausting the tier=2 capacity), sees 3 still pending and
    // redispatches, and that redispatch immediately sees 0 remaining
    // capacity and marks the broadcast 'throttled' before returning.
    (new SendWhatsappBroadcastBatch($broadcast->id))->handle(app(\App\Services\WhatsApp\OutboundMessageSender::class));

    // Only 2 (the tier limit) should have been sent; the rest stay pending.
    expect(WhatsappBroadcastRecipient::query()->where('status', 'sent')->count())->toBe(2);
    expect(WhatsappBroadcastRecipient::query()->where('status', 'pending')->count())->toBe(3);
    expect($broadcast->fresh()->status)->toBe('throttled');
});

it('skips an opted-out recipient without calling the API for them', function (): void {
    Http::fake([
        '*/messages' => Http::response(['messages' => [['id' => 'wamid.Y']]], 200),
    ]);

    $broadcast = makeBroadcastWithRecipients(1);
    $broadcast->recipients()->first()->contact->forceFill(['opt_in_status' => 'opted_out'])->save();

    (new SendWhatsappBroadcastBatch($broadcast->id))->handle(app(\App\Services\WhatsApp\OutboundMessageSender::class));

    expect($broadcast->recipients()->first()->status)->toBe('skipped');
    Http::assertNothingSent();
});

it('marks a recipient failed when the provider rejects the send, without stopping the batch', function (): void {
    Http::fake([
        '*/messages' => Http::sequence()
            ->push(['error' => ['code' => 131047, 'message' => 'Re-engagement message']], 400)
            ->push(['messages' => [['id' => 'wamid.OK']]], 200),
    ]);

    $broadcast = makeBroadcastWithRecipients(2);

    (new SendWhatsappBroadcastBatch($broadcast->id))->handle(app(\App\Services\WhatsApp\OutboundMessageSender::class));

    expect(WhatsappBroadcastRecipient::query()->where('status', 'sent')->count())->toBe(1);
    expect(WhatsappBroadcastRecipient::query()->where('status', 'failed')->count())->toBe(1);
    expect($broadcast->fresh()->failed_count)->toBe(1);
});
