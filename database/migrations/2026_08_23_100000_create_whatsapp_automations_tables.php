<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `steps` is an ordered JSON array of step definitions, each shaped
     * like {"type": "send_template", ...} / {"type": "wait", "minutes":
     * 60} / {"type": "condition", "true_step": 3, "false_step": 5} /
     * {"type": "add_tag"|"remove_tag", "tag_id": N} /
     * {"type": "webhook", "url": "...", "method": "POST"}. See
     * AutomationStepExecutor for the authoritative step contract.
     */
    public function up(): void
    {
        Schema::create('wa_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('wa_phone_number_id')->nullable()->constrained('wa_phone_numbers')->cascadeOnDelete();
            $table->string('name');
            // contact_created | keyword_received | opted_in
            $table->string('trigger_type');
            $table->json('trigger_config')->nullable();
            $table->json('steps');
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wa_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_automation_id')->constrained('wa_automations')->cascadeOnDelete();
            $table->foreignId('wa_contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            // running | waiting | completed | failed
            $table->string('status')->default('running');
            $table->unsignedInteger('current_step_index')->default(0);
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('resume_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'resume_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_automation_runs');
        Schema::dropIfExists('wa_automations');
    }
};
