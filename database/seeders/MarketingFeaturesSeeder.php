<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ensures every existing branch has a gym_settings row with the WhatsApp
 * marketing modules enabled, so freshly deployed branches can reach the
 * Broadcasts/Automations/Knowledge Base screens (each resource's canAccess
 * gates on these per-branch flags). Idempotent: existing rows keep whatever
 * the branch has already saved — only a missing row is created with the
 * flags on. Branch admins can still toggle everything in Settings.
 */
class MarketingFeaturesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $gymIds = DB::table('gyms')->pluck('id');

        foreach ($gymIds as $gymId) {
            $exists = DB::table('gym_settings')->where('gym_id', $gymId)->exists();

            if ($exists) {
                continue;
            }

            DB::table('gym_settings')->insert([
                'gym_id' => $gymId,
                'data' => json_encode([
                    'marketing' => [
                        'inbox' => true,
                        'broadcasts' => true,
                        'automations' => true,
                        'knowledge_base' => true,
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
