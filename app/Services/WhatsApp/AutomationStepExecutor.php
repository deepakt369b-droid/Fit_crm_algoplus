<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappAutomation;
use App\Models\WhatsappAutomationRun;
use App\Models\WhatsappContact;
use App\Models\WhatsappConversation;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Executes exactly one automation step and reports what the caller
 * (ProcessWhatsappAutomationRun) should do next. Never advances the run
 * itself — the job owns that, so it can enforce the per-invocation and
 * per-run step caps that keep a misconfigured branch (e.g. a condition
 * that jumps back on itself) from looping forever.
 *
 * @phpstan-type StepOutcome array{action: string, step_index?: int, minutes?: int, error?: string}
 */
class AutomationStepExecutor
{
    public function __construct(private readonly OutboundMessageSender $sender) {}

    /**
     * @param  array<string, mixed>  $step
     * @return array{action: string, step_index?: int, minutes?: int, error?: string}
     */
    public function execute(WhatsappAutomation $automation, WhatsappAutomationRun $run, array $step): array
    {
        $contact = $run->contact;

        return match ($step['type'] ?? null) {
            'send_template' => $this->sendTemplate($automation, $contact, $step),
            'add_tag' => $this->setTag($contact, $step, attach: true),
            'remove_tag' => $this->setTag($contact, $step, attach: false),
            'wait' => ['action' => 'wait', 'minutes' => max(1, (int) ($step['minutes'] ?? 60))],
            'condition' => $this->evaluateCondition($contact, $step),
            'webhook' => $this->callWebhook($contact, $step),
            default => ['action' => 'fail', 'error' => 'Unknown step type: '.json_encode($step['type'] ?? null)],
        };
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{action: string, error?: string}
     */
    private function sendTemplate(WhatsappAutomation $automation, WhatsappContact $contact, array $step): array
    {
        if ($automation->wa_phone_number_id === null) {
            return ['action' => 'fail', 'error' => 'This automation has no phone number configured.'];
        }

        $template = WhatsappTemplate::query()->find($step['template_id'] ?? null);

        if ($template === null) {
            return ['action' => 'fail', 'error' => 'Template not found for send_template step.'];
        }

        $conversation = WhatsappConversation::query()->firstOrCreate(
            ['wa_phone_number_id' => $automation->wa_phone_number_id, 'wa_contact_id' => $contact->id],
            ['gym_id' => $automation->gym_id ?? $contact->gym_id, 'status' => 'open'],
        );

        $variables = array_map(
            fn (string $value): string => $value === '{{contact.name}}' ? (string) $contact->name : $value,
            $step['variables'] ?? [],
        );

        $components = $variables === [] ? [] : [[
            'type' => 'body',
            'parameters' => array_map(fn (string $value): array => ['type' => 'text', 'text' => $value], $variables),
        ]];

        try {
            $message = $this->sender->sendTemplate($conversation, $template, $components);

            return $message->status === 'failed'
                ? ['action' => 'fail', 'error' => $message->error_message ?? 'Send failed']
                : ['action' => 'advance'];
        } catch (RuntimeException $exception) {
            return ['action' => 'fail', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{action: string}
     */
    private function setTag(WhatsappContact $contact, array $step, bool $attach): array
    {
        $tagId = $step['tag_id'] ?? null;

        if ($tagId !== null) {
            if ($attach) {
                $contact->tags()->syncWithoutDetaching([$tagId]);
            } else {
                $contact->tags()->detach([$tagId]);
            }
        }

        return ['action' => 'advance'];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{action: string, step_index: int}
     */
    private function evaluateCondition(WhatsappContact $contact, array $step): array
    {
        $field = (string) ($step['field'] ?? '');
        $operator = (string) ($step['operator'] ?? 'equals');
        $expected = $step['value'] ?? null;
        $actual = data_get($contact, $field);

        $result = match ($operator) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'has_tag' => $contact->tags()->where('wa_tags.id', $expected)->exists(),
            default => false,
        };

        $nextIndex = $result ? ($step['true_step'] ?? null) : ($step['false_step'] ?? null);

        return ['action' => 'jump', 'step_index' => (int) ($nextIndex ?? 0)];
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{action: string, error?: string}
     */
    private function callWebhook(WhatsappContact $contact, array $step): array
    {
        $url = (string) ($step['url'] ?? '');

        if ($url === '' || ! str_starts_with($url, 'http')) {
            return ['action' => 'fail', 'error' => 'webhook step has no valid url.'];
        }

        try {
            $response = Http::timeout(10)->send(strtoupper((string) ($step['method'] ?? 'POST')), $url, [
                'json' => [
                    'contact_id' => $contact->id,
                    'phone' => $contact->phone,
                    'name' => $contact->name,
                ],
            ]);

            if ($response->failed()) {
                Log::warning('WhatsApp automation webhook step returned an error status', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $exception) {
            // A broken external webhook (connection refused, DNS failure,
            // timeout, or a non-2xx status above) shouldn't be able to
            // wedge every contact's automation run - log it and move on.
            Log::warning('WhatsApp automation webhook step failed', ['url' => $url, 'error' => $exception->getMessage()]);
        }

        return ['action' => 'advance'];
    }
}
