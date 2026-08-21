<?php

use App\Models\Gym;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\TemplateSyncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('syncs templates from the Graph API into the local cache', function (): void {
    Http::fake([
        '*/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-1',
                    'name' => 'welcome_message',
                    'language' => 'en_US',
                    'category' => 'MARKETING',
                    'status' => 'APPROVED',
                    'components' => [['type' => 'BODY', 'text' => 'Welcome!']],
                ],
            ],
        ], 200),
    ]);

    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();

    $count = app(TemplateSyncer::class)->sync($phoneNumber);

    expect($count)->toBe(1);

    $template = WhatsappTemplate::query()->where('name', 'welcome_message')->firstOrFail();
    expect($template->status)->toBe('approved');
});

it('does not choke on a template status value outside Meta\'s common set', function (): void {
    // wa_templates.status is a plain string column specifically so that an
    // unrecognized value from Meta (their vocabulary is larger than
    // approved/pending/rejected — e.g. paused, disabled, in_appeal,
    // pending_deletion) never hard-fails the whole sync batch.
    Http::fake([
        '*/message_templates*' => Http::response([
            'data' => [
                [
                    'id' => 'tpl-2',
                    'name' => 'appeal_template',
                    'language' => 'en_US',
                    'category' => 'UTILITY',
                    'status' => 'IN_APPEAL',
                    'components' => [],
                ],
            ],
        ], 200),
    ]);

    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();

    $count = app(TemplateSyncer::class)->sync($phoneNumber);

    expect($count)->toBe(1);

    $template = WhatsappTemplate::query()->where('name', 'appeal_template')->firstOrFail();
    expect($template->status)->toBe('in_appeal');
});

it('re-syncing updates an existing template instead of duplicating it', function (): void {
    Http::fake([
        '*/message_templates*' => Http::response([
            'data' => [[
                'id' => 'tpl-3',
                'name' => 'reminder',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => 'PENDING',
                'components' => [],
            ]],
        ], 200),
    ]);

    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();

    app(TemplateSyncer::class)->sync($phoneNumber);

    Http::fake([
        '*/message_templates*' => Http::response([
            'data' => [[
                'id' => 'tpl-3',
                'name' => 'reminder',
                'language' => 'en_US',
                'category' => 'UTILITY',
                'status' => 'APPROVED',
                'components' => [],
            ]],
        ], 200),
    ]);

    app(TemplateSyncer::class)->sync($phoneNumber);

    expect(WhatsappTemplate::query()->where('name', 'reminder')->count())->toBe(1);
    expect(WhatsappTemplate::query()->where('name', 'reminder')->first()->status)->toBe('approved');
});
