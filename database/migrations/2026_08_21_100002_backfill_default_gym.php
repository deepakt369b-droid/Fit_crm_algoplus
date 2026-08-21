<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables backfilled onto the default branch.
     *
     * @var list<string>
     */
    private const TABLES = [
        'users',
        'members',
        'plans',
        'services',
        'subscriptions',
        'invoices',
        'invoice_transactions',
        'expenses',
        'enquiries',
        'follow_ups',
    ];

    private const DEFAULT_SLUG = 'main-branch';

    /**
     * Run the migrations.
     *
     * Any install created before multi-branch support existed has every row
     * with a null gym_id. Assign them all to a single default "Main Branch"
     * gym so existing installs keep working exactly as before, just now
     * inside branch #1 instead of unscoped.
     */
    public function up(): void
    {
        $gymId = DB::table('gyms')->where('slug', self::DEFAULT_SLUG)->value('id');

        if ($gymId === null) {
            $gymId = DB::table('gyms')->insertGetId([
                'name' => 'Main Branch',
                'slug' => self::DEFAULT_SLUG,
                'status' => 'active',
                'timezone' => config('app.timezone', 'UTC'),
                'currency' => 'USD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::TABLES as $tableName) {
            DB::table($tableName)->whereNull('gym_id')->update(['gym_id' => $gymId]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Not a true inverse (there is no way to know which rows were null
     * before this ran), but restores the pre-migration state for the
     * common case: null out gym_id wherever it points at the default
     * branch, then remove that branch.
     */
    public function down(): void
    {
        $gymId = DB::table('gyms')->where('slug', self::DEFAULT_SLUG)->value('id');

        if ($gymId === null) {
            return;
        }

        foreach (self::TABLES as $tableName) {
            DB::table($tableName)->where('gym_id', $gymId)->update(['gym_id' => null]);
        }

        DB::table('gyms')->where('id', $gymId)->delete();
    }
};
