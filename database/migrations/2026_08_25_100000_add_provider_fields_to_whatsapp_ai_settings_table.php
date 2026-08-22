<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Broadens the AI reply assistant from Anthropic-only to a
     * provider-neutral setup (Anthropic, OpenAI, Kimi, GLM — see
     * config/services.php's ai_providers list and
     * App\Services\WhatsApp\Ai\AiChatClientFactory). Additive only:
     * anthropic_api_key is left in place rather than dropped/renamed
     * (a destructive change), but application code no longer reads or
     * writes it as of this change — api_key/provider are now
     * authoritative. Existing rows have their key copied over.
     */
    public function up(): void
    {
        Schema::table('wa_ai_settings', function (Blueprint $table) {
            $table->string('provider')->default('anthropic')->after('gym_id');
            $table->text('api_key')->nullable()->after('provider');
            $table->string('base_url')->nullable()->after('model');
        });

        // anthropic_api_key is stored via Eloquent's 'encrypted' cast,
        // so its raw column value is already ciphertext under the
        // app's own APP_KEY - copying the column value directly (no
        // decrypt/re-encrypt) keeps it decryptable through the new
        // api_key column's identical 'encrypted' cast.
        DB::table('wa_ai_settings')
            ->whereNotNull('anthropic_api_key')
            ->update(['api_key' => DB::raw('anthropic_api_key')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wa_ai_settings', function (Blueprint $table) {
            $table->dropColumn(['provider', 'api_key', 'base_url']);
        });
    }
};
