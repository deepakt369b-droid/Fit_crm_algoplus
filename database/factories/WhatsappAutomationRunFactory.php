<?php

namespace Database\Factories;

use App\Models\WhatsappAutomation;
use App\Models\WhatsappContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappAutomationRun>
 */
class WhatsappAutomationRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wa_automation_id' => WhatsappAutomation::factory(),
            'wa_contact_id' => WhatsappContact::factory(),
            'status' => 'running',
            'current_step_index' => 0,
        ];
    }
}
