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
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();
            // Nullable for the same reason as wa_contacts.gym_id: a
            // conversation started on a shared number inherits its
            // contact's (possibly null) branch.
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('wa_phone_number_id')->constrained('wa_phone_numbers')->cascadeOnDelete();
            $table->foreignId('wa_contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['wa_phone_number_id', 'wa_contact_id']);
        });

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('wa_conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->string('type')->default('text');
            $table->string('meta_message_id')->nullable()->unique();
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('queued');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->text('body')->nullable();
            $table->string('template_name')->nullable();
            $table->string('media_url')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['wa_conversation_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('wa_conversations');
    }
};
