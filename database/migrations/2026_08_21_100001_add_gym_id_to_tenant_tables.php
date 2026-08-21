<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables scoped to a branch (Gym) in a multi-branch install.
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

    /**
     * Run the migrations.
     *
     * gym_id is added nullable rather than NOT NULL: the next migration
     * backfills every existing row onto a default branch, but a hard
     * NOT NULL constraint (via Schema::table(...)->change()) needs
     * doctrine/dbal, which this project does not install. Application code
     * (BelongsToGym + GymScope) treats a null gym_id as "unscoped" and
     * always fills it on create, so this is enforced at the app layer
     * instead of the schema layer.
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('gym_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('gyms')
                    ->restrictOnDelete();

                $table->index('gym_id', $tableName.'_gym_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign([$tableName.'_gym_id_foreign']);
                $table->dropIndex($tableName.'_gym_id_index');
                $table->dropColumn('gym_id');
            });
        }
    }
};
