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
        Schema::create('wa_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('wa_phone_number_id')->constrained('wa_phone_numbers')->cascadeOnDelete();
            $table->foreignId('wa_template_id')->constrained('wa_templates')->cascadeOnDelete();
            $table->string('name');
            // Plain string, not an enum, for the same reason as
            // wa_templates.status: this list has already grown once
            // during design (throttled/paused) and will again.
            $table->string('status')->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('wa_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_broadcast_id')->constrained('wa_broadcasts')->cascadeOnDelete();
            $table->foreignId('wa_contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('wa_message_id')->nullable()->constrained('wa_messages')->nullOnDelete();
            // pending -> sent|failed|skipped|throttled. delivered/read are
            // filled in later by the same status webhook that already
            // updates wa_messages (see InboundWebhookProcessor).
            $table->string('status')->default('pending');
            $table->json('variables')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['wa_broadcast_id', 'wa_contact_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_broadcast_recipients');
        Schema::dropIfExists('wa_broadcasts');
    }
};
