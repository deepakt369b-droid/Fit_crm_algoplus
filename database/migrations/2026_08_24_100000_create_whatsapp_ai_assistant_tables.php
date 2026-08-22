<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wa_ai_settings', function (Blueprint $table) {
            $table->id();
            // Nullable and unique per the established wa_* pattern (see
            // wa_phone_numbers/wa_automations) - a null-gym row is only
            // ever written from a superadmin/no-tenant context. A unique
            // index still allows multiple NULLs under MySQL, so this only
            // constrains one row per *actual* branch.
            $table->foreignId('gym_id')->nullable()->unique()->constrained('gyms')->cascadeOnDelete();
            $table->text('anthropic_api_key')->nullable();
            $table->string('model')->default('claude-opus-5');
            $table->text('system_prompt')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->timestamps();

            $table->index('gym_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_knowledge_base_articles');
        Schema::dropIfExists('wa_ai_settings');
    }
};
