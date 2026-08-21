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
     * Settings move from a single shared `storage/data/settingsData.json`
     * file to one row per branch here. This closes two problems the JSON
     * file has in a multi-branch, container-deployed install: every branch
     * would otherwise share one gym_name/logo/invoice-prefix, and on
     * Coolify a redeploy replaces the container filesystem, silently
     * discarding the file unless a volume happens to be mounted over it.
     */
    public function up(): void
    {
        Schema::create('gym_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->unique()->constrained('gyms')->cascadeOnDelete();
            $table->json('data');
            $table->timestamps();
        });

        $this->importExistingJsonSettings();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_settings');
    }

    /**
     * One-time import of the legacy JSON settings file (if present) into the
     * default branch's row, so upgrading installs keep their configuration.
     */
    private function importExistingJsonSettings(): void
    {
        $path = storage_path('data/settingsData.json');

        if (! file_exists($path)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return;
        }

        $gymId = DB::table('gyms')->where('slug', 'main-branch')->value('id');

        DB::table('gym_settings')->updateOrInsert(
            ['gym_id' => $gymId],
            ['data' => json_encode($decoded), 'created_at' => now(), 'updated_at' => now()],
        );
    }
};
