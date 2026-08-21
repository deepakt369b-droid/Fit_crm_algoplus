<?php

namespace Database\Seeders;

use App\Contracts\TenantContext;
use App\Models\Gym;
use Illuminate\Database\Seeder;
use Nnjeim\World\Actions\SeedAction;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedWorldData();
        $this->bindDefaultBranchTenantContext();
        $this->callConfiguredSeeders('fitcrm.seeding.before');

        $this->call([
            ShieldSeeder::class,
            UserSeeder::class,
            ServiceSeeder::class,
            PlanSeeder::class,
            EnquirySeeder::class,
            FollowUpSeeder::class,
            MemberSeeder::class,
            SubscriptionSeeder::class,
            InvoiceSeeder::class,
            ExpenseSeeder::class,
        ]);

        $this->callConfiguredSeeders('fitcrm.seeding.after');

        if (app()->environment(['local', 'development'])) {
            $this->call(DashboardDemoSeeder::class);
        }
    }

    /**
     * Bind every seeded record to a single default branch.
     *
     * Seeders run in console context, where TenantContext resolves to no
     * branch (see ResolvedTenantContext) — without this, every seeded
     * record would end up with a null gym_id, invisible to any branch
     * admin scoped to a real branch, even though a "Main Branch" gym also
     * exists (created by the gym_id migrations). Binding a fixed
     * TenantContext here makes BelongsToGym auto-fill every seeded record
     * onto that branch instead, exactly like a real request from an admin
     * of that branch would.
     */
    private function bindDefaultBranchTenantContext(): void
    {
        $gym = Gym::query()->firstOrCreate(
            ['slug' => 'main-branch'],
            [
                'name' => 'Main Branch',
                'status' => 'active',
                'timezone' => config('app.timezone', 'UTC'),
                'currency' => 'USD',
            ],
        );

        app()->instance(TenantContext::class, new class($gym->id) implements TenantContext
        {
            public function __construct(private readonly int $gymId) {}

            public function gymId(): ?int
            {
                return $this->gymId;
            }
        });
    }

    /**
     * Seed supporting world data when the package is available.
     */
    private function seedWorldData(): void
    {
        try {
            $this->call(SeedAction::class);
        } catch (Throwable $e) {
            $this->command?->warn('Skipping world data seed: '.$e->getMessage());
        }
    }

    private function callConfiguredSeeders(string $configKey): void
    {
        $seeders = array_values(array_filter(
            (array) config($configKey, []),
            fn (mixed $seeder): bool => is_string($seeder) && is_subclass_of($seeder, Seeder::class),
        ));

        if ($seeders !== []) {
            $this->call($seeders);
        }
    }
}
