<?php

namespace App\Services\WhatsApp;

use App\Jobs\ProcessWhatsappAutomationRun;
use App\Models\WhatsappAutomation;
use App\Models\WhatsappAutomationRun;
use App\Models\WhatsappContact;

/**
 * Matches an inbound-message event against active automations and
 * starts a run for each one that fires.
 */
class AutomationTriggerService
{
    /**
     * @param  'contact_created'|'opted_in'  $event
     */
    public function fireEvent(string $event, WhatsappContact $contact, ?int $phoneNumberId = null): void
    {
        $automations = WhatsappAutomation::query()
            ->where('status', 'active')
            ->where('trigger_type', $event)
            ->when(
                $phoneNumberId !== null,
                fn ($query) => $query->where(function ($query) use ($phoneNumberId) {
                    $query->whereNull('wa_phone_number_id')->orWhere('wa_phone_number_id', $phoneNumberId);
                }),
            )
            ->get();

        foreach ($automations as $automation) {
            $this->start($automation, $contact);
        }
    }

    public function fireKeyword(string $body, WhatsappContact $contact, ?int $phoneNumberId = null): void
    {
        $normalized = strtolower(trim($body));

        if ($normalized === '') {
            return;
        }

        $automations = WhatsappAutomation::query()
            ->where('status', 'active')
            ->where('trigger_type', 'keyword_received')
            ->when(
                $phoneNumberId !== null,
                fn ($query) => $query->where(function ($query) use ($phoneNumberId) {
                    $query->whereNull('wa_phone_number_id')->orWhere('wa_phone_number_id', $phoneNumberId);
                }),
            )
            ->get()
            ->filter(fn (WhatsappAutomation $automation): bool => strtolower(trim(
                (string) data_get($automation->trigger_config, 'keyword', '')
            )) === $normalized);

        foreach ($automations as $automation) {
            $this->start($automation, $contact);
        }
    }

    private function start(WhatsappAutomation $automation, WhatsappContact $contact): void
    {
        $run = WhatsappAutomationRun::query()->create([
            'wa_automation_id' => $automation->id,
            'wa_contact_id' => $contact->id,
            'status' => 'running',
        ]);

        ProcessWhatsappAutomationRun::dispatch($run->id);
    }
}
