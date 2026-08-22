<?php

use App\Contracts\UrlSafetyChecker;
use App\Models\Gym;
use App\Models\WhatsappAutomation;
use App\Models\WhatsappAutomationRun;
use App\Models\WhatsappContact;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTag;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\AutomationStepExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * These tests exercise AutomationStepExecutor's own logic, not DNS
 * resolution — bind a permissive fake so the real DnsUrlSafetyChecker
 * (which does a live DNS lookup) never runs against network-dependent
 * or intentionally non-resolving test domains like example.test.
 * The SSRF guard itself has its own dedicated unit tests — see
 * tests/Unit/Services/WhatsApp/Support/DnsUrlSafetyCheckerTest.php.
 */
function bindPermissiveUrlSafetyChecker(): void
{
    app()->bind(UrlSafetyChecker::class, fn () => new class implements UrlSafetyChecker
    {
        public function isSafe(string $url): bool
        {
            return true;
        }
    });
}

function bindUnsafeUrlSafetyChecker(): void
{
    app()->bind(UrlSafetyChecker::class, fn () => new class implements UrlSafetyChecker
    {
        public function isSafe(string $url): bool
        {
            return false;
        }
    });
}

function makeAutomationRun(array $automationOverrides = [], array $contactOverrides = []): WhatsappAutomationRun
{
    $gym = Gym::factory()->create();
    $phoneNumber = WhatsappPhoneNumber::factory()->for($gym)->create();
    $contact = WhatsappContact::factory()->for($gym)->create($contactOverrides);

    $automation = WhatsappAutomation::query()->create(array_merge([
        'gym_id' => $gym->id,
        'wa_phone_number_id' => $phoneNumber->id,
        'name' => 'Test automation',
        'trigger_type' => 'contact_created',
        'steps' => [],
        'status' => 'active',
    ], $automationOverrides));

    return WhatsappAutomationRun::query()->create([
        'wa_automation_id' => $automation->id,
        'wa_contact_id' => $contact->id,
        'status' => 'running',
    ]);
}

it('sends a template message and advances', function (): void {
    Http::fake([
        '*/messages' => Http::response(['messages' => [['id' => 'wamid.A1']]], 200),
    ]);

    $run = makeAutomationRun();
    $template = WhatsappTemplate::factory()->for($run->automation->phoneNumber, 'phoneNumber')->create(['status' => 'approved']);

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, [
        'type' => 'send_template',
        'template_id' => $template->id,
        'variables' => ['{{contact.name}}'],
    ]);

    expect($outcome['action'])->toBe('advance');
});

it('fails the step when the automation has no phone number', function (): void {
    $run = makeAutomationRun(['wa_phone_number_id' => null]);

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, [
        'type' => 'send_template',
        'template_id' => 999,
    ]);

    expect($outcome['action'])->toBe('fail');
});

it('adds and removes a tag', function (): void {
    $run = makeAutomationRun();
    $tag = WhatsappTag::factory()->create();

    $executor = app(AutomationStepExecutor::class);

    $executor->execute($run->automation, $run, ['type' => 'add_tag', 'tag_id' => $tag->id]);
    expect($run->contact->tags()->count())->toBe(1);

    $executor->execute($run->automation, $run, ['type' => 'remove_tag', 'tag_id' => $tag->id]);
    expect($run->contact->fresh()->tags()->count())->toBe(0);
});

it('returns a wait outcome with the configured minutes', function (): void {
    $run = makeAutomationRun();

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, ['type' => 'wait', 'minutes' => 90]);

    expect($outcome)->toBe(['action' => 'wait', 'minutes' => 90]);
});

it('branches a condition to the true step when it matches', function (): void {
    $run = makeAutomationRun([], ['opt_in_status' => 'opted_in']);

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, [
        'type' => 'condition',
        'field' => 'opt_in_status',
        'operator' => 'equals',
        'value' => 'opted_in',
        'true_step' => 5,
        'false_step' => 9,
    ]);

    expect($outcome)->toBe(['action' => 'jump', 'step_index' => 5]);
});

it('branches a condition to the false step when it does not match', function (): void {
    $run = makeAutomationRun([], ['opt_in_status' => 'unknown']);

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, [
        'type' => 'condition',
        'field' => 'opt_in_status',
        'operator' => 'equals',
        'value' => 'opted_in',
        'true_step' => 5,
        'false_step' => 9,
    ]);

    expect($outcome)->toBe(['action' => 'jump', 'step_index' => 9]);
});

it('calls a webhook and advances even if the webhook endpoint fails', function (): void {
    bindPermissiveUrlSafetyChecker();
    Http::fake([
        'example.test/*' => Http::response('', 500),
    ]);

    $run = makeAutomationRun();

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, [
        'type' => 'webhook',
        'url' => 'https://example.test/hook',
        'method' => 'POST',
    ]);

    expect($outcome['action'])->toBe('advance');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/hook');
});

it('fails a webhook step whose URL is not safe, without making any request', function (): void {
    bindUnsafeUrlSafetyChecker();
    Http::fake();

    $run = makeAutomationRun();

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, [
        'type' => 'webhook',
        'url' => 'http://169.254.169.254/latest/meta-data/',
        'method' => 'POST',
    ]);

    expect($outcome['action'])->toBe('fail');
    Http::assertNothingSent();
});

it('fails a send_template step when the template belongs to a different phone number', function (): void {
    $run = makeAutomationRun();
    $otherPhoneNumber = WhatsappPhoneNumber::factory()->create();
    $foreignTemplate = WhatsappTemplate::factory()->for($otherPhoneNumber, 'phoneNumber')->create(['status' => 'approved']);

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, [
        'type' => 'send_template',
        'template_id' => $foreignTemplate->id,
    ]);

    expect($outcome['action'])->toBe('fail');
});

it('fails on an unknown step type', function (): void {
    $run = makeAutomationRun();

    $outcome = app(AutomationStepExecutor::class)->execute($run->automation, $run, ['type' => 'nonsense']);

    expect($outcome['action'])->toBe('fail');
});
