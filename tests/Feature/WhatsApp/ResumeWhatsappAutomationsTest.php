<?php

use App\Models\Gym;
use App\Models\WhatsappAutomation;
use App\Models\WhatsappAutomationRun;
use App\Models\WhatsappContact;
use App\Models\WhatsappPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resumes a waiting run whose resume_at has passed, and leaves a future one alone', function (): void {
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();
    $automation = WhatsappAutomation::query()->create([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'name' => 'Test automation',
        'trigger_type' => 'contact_created',
        'steps' => [],
        'status' => 'active',
    ]);

    $dueRun = WhatsappAutomationRun::query()->create([
        'wa_automation_id' => $automation->id,
        'wa_contact_id' => WhatsappContact::factory()->for($gym)->create()->id,
        'status' => 'waiting',
        'resume_at' => now()->subMinute(),
    ]);

    $futureRun = WhatsappAutomationRun::query()->create([
        'wa_automation_id' => $automation->id,
        'wa_contact_id' => WhatsappContact::factory()->for($gym)->create()->id,
        'status' => 'waiting',
        'resume_at' => now()->addHour(),
    ]);

    $this->artisan('fitcrm:automations:resume')->assertSuccessful();

    // steps=[] so the resumed run's job immediately completes it.
    expect($dueRun->fresh()->status)->toBe('completed');
    expect($futureRun->fresh()->status)->toBe('waiting');
});
