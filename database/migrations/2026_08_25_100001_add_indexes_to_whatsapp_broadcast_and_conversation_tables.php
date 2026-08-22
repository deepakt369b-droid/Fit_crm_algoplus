<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Two hot paths were missing supporting indexes (found during a
     * production-readiness audit):
     *
     * - SendWhatsappBroadcastBatch filters wa_broadcast_recipients by
     *   [wa_broadcast_id, status] on every paced batch tick. Only a
     *   unique index on [wa_broadcast_id, wa_contact_id] existed, which
     *   doesn't support a status filter — fine at hundreds of
     *   recipients, degrades at thousands.
     * - The shared-inbox table (WhatsappConversationTable) polls every
     *   20s with a [gym_id]-scoped, status-filtered, last_message_at
     *   sort. Only a unique index on [wa_phone_number_id, wa_contact_id]
     *   existed, which doesn't support that query shape either.
     */
    public function up(): void
    {
        Schema::table('wa_broadcast_recipients', function (Blueprint $table) {
            $table->index(['wa_broadcast_id', 'status']);
        });

        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->index(['gym_id', 'status', 'last_message_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wa_broadcast_recipients', function (Blueprint $table) {
            $table->dropIndex(['wa_broadcast_id', 'status']);
        });

        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropIndex(['gym_id', 'status', 'last_message_at']);
        });
    }
};
