<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Local cache of Meta-approved templates, synced from the Graph API.
     * Scoped by which phone number synced them (templates are approved at
     * the WABA level and shared by every number under it); gym_id mirrors
     * the number's own branch (null for a shared number).
     */
    public function up(): void
    {
        Schema::create('wa_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('wa_phone_number_id')->constrained('wa_phone_numbers')->cascadeOnDelete();
            $table->string('meta_template_id')->nullable();
            $table->string('name');
            $table->string('language', 10);
            $table->string('category')->nullable();
            // Plain string, not an enum: this vocabulary is Meta's, not
            // ours (approved/pending/rejected/paused/disabled/in_appeal/
            // pending_deletion/...), and a value we haven't enumerated
            // would otherwise hard-fail the whole sync on a DB constraint.
            $table->string('status')->default('pending');
            $table->json('components')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['wa_phone_number_id', 'name', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
    }
};
