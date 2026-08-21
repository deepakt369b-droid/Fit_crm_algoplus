<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Meta's messaging tiers cap unique new-conversation recipients per
     * rolling 24h (250 -> 1K -> 10K -> 100K), assigned by Meta based on
     * quality/volume — not something this app sets. This column is the
     * admin's own record of their number's *current* tier ceiling, used
     * to stop a broadcast before it draws error 131042 ("frequency limit
     * reached") rather than after. It has to be updated by hand as Meta
     * upgrades the number; there is no API to read the tier directly.
     */
    public function up(): void
    {
        Schema::table('wa_phone_numbers', function (Blueprint $table) {
            $table->unsignedInteger('messaging_tier_limit')->default(250)->after('is_shared');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wa_phone_numbers', function (Blueprint $table) {
            $table->dropColumn('messaging_tier_limit');
        });
    }
};
