<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dedicated super-admin operator account, separate from the shared
 * test@example.com login. Runs in testing deployments only (see
 * scripts/coolify-deploy.sh) so the credentials below are safe to
 * document — production must create its own account and delete both
 * seeded ones before going live.
 */
class SuperAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public const EMAIL = 'superadmin@fitcrm.local';

    public const PASSWORD = 'FitCRM-Super-2026!';

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(self::PASSWORD),
                'status' => 'active',
            ],
        );

        $user->assignRole('super_admin');
    }
}
