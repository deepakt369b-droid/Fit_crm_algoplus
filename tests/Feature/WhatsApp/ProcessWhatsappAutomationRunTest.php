<?php

use App\Jobs\ProcessWhatsappAutomationRun;
use App\Models\Gym;
use App\Models\WhatsappAutomation;
use App\Models\WhatsappAutomationRun;
use App\Models\WhatsappContact;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTag;
use App\Services\WhatsApp\AutomationStepExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function startAutomationRun(array $steps, array $automationOverrides = []): WhatsappAutomationRun
{
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();
    $contact = WhatsappContact::factory()->for($gym)->create();

    $automation = WhatsappAutomation::query()->create(array_merge([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'name' => 'Test automation',
        'trigger_type' => 'contact_created',
        'steps' => $steps,
        'status' => 'active',
    ], $automationOverrides));

    return WhatsappAutomationRun::query()->create([
        'wa_automation_id' => $automation->id,
        'wa_contact_id' => $contact->id,
        'status' => 'running',
    ]);
}

it('runs every step in sequence and completes', function (): void {
    $tag = WhatsappTag::factory()->create();

    $run = startAutomationRun([
        ['type' => 'add_tag', 'tag_id' => $tag->id],
        ['type' => 'remove_tag', 'tag_id' => $tag->id],
    ]);

    (new ProcessWhatsappAutomationRun($run->id))->handle(app(AutomationStepExecutor::class));

    expect($run->fresh())
        ->status->toBe('completed')
        ->current_step_index->toBe(2);
});

it('pauses at a wait step and sets resume_at', function (): void {
    $run = startAutomationRun([
        ['type' => 'wait', 'minutes' => 60],
        ['type' => 'add_tag', 'tag_id' => WhatsappTag::factory()->create()->id],
    ]);

    (new ProcessWhatsappAutomationRun($run->id))->handle(app(AutomationStepExecutor::class));

    $fresh = $run->fresh();
    expect($fresh->status)->toBe('waiting')
        ->and($fresh->current_step_index)->toBe(1)
        ->and($fresh->resume_at)->not->toBeNull()
        ->and($fresh->resume_at->diffInMinutes(now()))->toBeLessThan(61);
});

it('resumes from where it left off after a wait', function (): void {
    $tag = WhatsappTag::factory()->create();

    $run = startAutomationRun([
        ['type' => 'wait', 'minutes' => 60],
        ['type' => 'add_tag', 'tag_id' => $tag->id],
    ]);

    (new ProcessWhatsappAutomationRun($run->id))->handle(app(AutomationStepExecutor::class));
    expect($run->fresh()->status)->toBe('waiting');

    // Simulate the scheduled resume sweep marking it running again.
    $run->fresh()->forceFill(['status' => 'running'])->save();
    (new ProcessWhatsappAutomationRun($run->id))->handle(app(AutomationStepExecutor::class));

    expect($run->fresh()->status)->toBe('completed');
    expect($run->contact->tags()->count())->toBe(1);
});

it('fails a run whose condition steps loop back on each other, instead of running forever', function (): void {
    // Two conditions that just point at each other forever.
    $run = startAutomationRun([
        ['type' => 'condition', 'field' => 'id', 'operator' => 'equals', 'value' => -1, 'true_step' => 1, 'false_step' => 1],
        ['type' => 'condition', 'field' => 'id', 'operator' => 'equals', 'value' => -1, 'true_step' => 0, 'false_step' => 0],
    ]);

    (new ProcessWhatsappAutomationRun($run->id))->handle(app(AutomationStepExecutor::class));

    $fresh = $run->fresh();
    expect($fresh->status)->toBe('failed')
        ->and($fresh->error_message)->toContain('exceeded');
});

it('fails immediately if the automation is inactive', function (): void {
    $run = startAutomationRun([
        ['type' => 'add_tag', 'tag_id' => WhatsappTag::factory()->create()->id],
    ], ['status' => 'inactive']);

    (new ProcessWhatsappAutomationRun($run->id))->handle(app(AutomationStepExecutor::class));

    expect($run->fresh()->status)->toBe('failed');
});

it('does nothing for an already-finished run', function (): void {
    $run = startAutomationRun([['type' => 'add_tag', 'tag_id' => WhatsappTag::factory()->create()->id]]);
    $run->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

    (new ProcessWhatsappAutomationRun($run->id))->handle(app(AutomationStepExecutor::class));

    expect($run->fresh()->current_step_index)->toBe(0);
});
