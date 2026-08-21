<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplate;

/**
 * Pulls a phone number's WABA-approved templates from the Graph API into
 * the local wa_templates cache.
 */
class TemplateSyncer
{
    /**
     * @return int Number of templates synced.
     */
    public function sync(WhatsappPhoneNumber $phoneNumber): int
    {
        $client = new MetaCloudApiClient($phoneNumber);
        $templates = $client->fetchTemplates();

        foreach ($templates as $template) {
            WhatsappTemplate::query()->updateOrCreate(
                [
                    'wa_phone_number_id' => $phoneNumber->id,
                    'name' => (string) ($template['name'] ?? ''),
                    'language' => (string) ($template['language'] ?? ''),
                ],
                [
                    'gym_id' => $phoneNumber->gym_id,
                    'meta_template_id' => $template['id'] ?? null,
                    'category' => $template['category'] ?? null,
                    'status' => strtolower((string) ($template['status'] ?? 'pending')),
                    'components' => $template['components'] ?? null,
                    'synced_at' => now(),
                ],
            );
        }

        return count($templates);
    }
}
