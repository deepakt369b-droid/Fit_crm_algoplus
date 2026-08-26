<?php

namespace Database\Seeders;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Testing-deployment fixture for multi-branch verification (handoff Loop 4):
 * a second branch plus an operator confined to it. Runs only from
 * scripts/coolify-deploy.sh under APP_ENV=testing — never in production.
 */
class BranchSeeder extends Seeder
{
    use WithoutModelEvents;

    public const OPERATOR_EMAIL = 'branchb@fitcrm.local';

    public const OPERATOR_PASSWORD = 'BranchB-Staff-2026!';

    public function run(): void
    {
        // ShieldSeeder only creates super_admin; assignRole throws
        // RoleDoesNotExist unless the role exists first.
        Role::findOrCreate('branch_staff', 'web');

        $branchB = Gym::query()->firstOrCreate(
            ['slug' => 'branch-b'],
            [
                'name' => 'Branch B',
                'status' => 'active',
                'timezone' => config('app.timezone', 'UTC'),
                'currency' => 'USD',
            ],
        );

        $operator = User::query()->firstOrCreate(
            ['email' => self::OPERATOR_EMAIL],
            [
                'name' => 'Branch B Staff',
                'password' => Hash::make(self::OPERATOR_PASSWORD),
                'status' => 'active',
                'gym_id' => $branchB->id,
            ],
        );

        $operator->assignRole('branch_staff');
    }
}
