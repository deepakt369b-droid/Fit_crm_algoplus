<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ensures every branch can reach the WhatsApp marketing screens (each
 * resource's canAccess gates on these per-branch flags).
 *
 * Testing deploys: flags are forced ON for every branch on every deploy, so
 * QA always sees the full surface regardless of what a previous session
 * saved. Production deploys: only a MISSING row is created (with the flags
 * on) — existing rows keep whatever the branch saved.
 */
class MarketingFeaturesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $testingFlags = json_encode([
            'marketing' => [
                'inbox' => true,
                'broadcasts' => true,
                'automations' => true,
                'knowledge_base' => true,
            ],
        ]);

        $force = app()->environment('testing');

        $gymIds = DB::table('gyms')->pluck('id');

        foreach ($gymIds as $gymId) {
            $row = DB::table('gym_settings')->where('gym_id', $gymId)->first();

            if ($row === null) {
                DB::table('gym_settings')->insert([
                    'gym_id' => $gymId,
                    'data' => $testingFlags,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            if (! $force) {
                continue;
            }

            $data = json_decode((string) $row->data, true);
            $data = is_array($data) ? $data : [];
            $data['marketing'] = array_merge(
                is_array($data['marketing'] ?? null) ? $data['marketing'] : [],
                ['inbox' => true, 'broadcasts' => true, 'automations' => true, 'knowledge_base' => true],
            );

            DB::table('gym_settings')->where('gym_id', $gymId)->update([
                'data' => json_encode($data),
                'updated_at' => now(),
            ]);
        }
    }
}
