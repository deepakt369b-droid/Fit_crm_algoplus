<?php

use App\Models\Gym;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappContact;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\BroadcastDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('creates a pending recipient row for each opted-in contact, skipping opted-out ones', function (): void {
    Queue::fake();

    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();
    $template = WhatsappTemplate::factory()->for($phoneNumber, 'phoneNumber')->create();
    $broadcast = WhatsappBroadcast::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'wa_template_id' => $template->id,
        'name' => 'Test broadcast',
        'status' => 'draft',
    ]);

    $optedIn = WhatsappContact::factory()->for($gym)->count(2)->create(['opt_in_status' => 'opted_in']);
    $optedOut = WhatsappContact::factory()->for($gym)->create(['opt_in_status' => 'opted_out']);

    $contacts = $optedIn->push($optedOut);

    app(BroadcastDispatcher::class)->dispatch($broadcast, $contacts);

    expect($broadcast->recipients()->count())->toBe(2)
        ->and($broadcast->recipients()->where('wa_contact_id', $optedOut->id)->exists())->toBeFalse()
        ->and($broadcast->fresh()->status)->toBe('scheduled')
        ->and($broadcast->fresh()->total_recipients)->toBe(2);
});

it('does not create duplicate recipient rows when dispatched twice for the same contact', function (): void {
    Queue::fake();

    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();
    $template = WhatsappTemplate::factory()->for($phoneNumber, 'phoneNumber')->create();
    $broadcast = WhatsappBroadcast::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'wa_template_id' => $template->id,
        'name' => 'Test broadcast',
        'status' => 'draft',
    ]);

    $contact = WhatsappContact::factory()->for($gym)->create(['opt_in_status' => 'opted_in']);

    $dispatcher = app(BroadcastDispatcher::class);
    $dispatcher->dispatch($broadcast, collect([$contact]));
    $dispatcher->dispatch($broadcast, collect([$contact]));

    expect($broadcast->recipients()->count())->toBe(1);
});
