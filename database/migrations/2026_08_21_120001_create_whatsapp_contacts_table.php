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
        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->id();
            // Nullable: a contact reaching in on a *shared* phone number
            // (see wa_phone_numbers.is_shared) can't be assigned a branch
            // automatically — it's left unscoped until staff assign it.
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->cascadeOnDelete();
            $table->nullableMorphs('contactable');
            $table->string('phone');
            $table->string('wa_id')->nullable();
            $table->string('name')->nullable();
            $table->enum('opt_in_status', ['unknown', 'opted_in', 'opted_out'])->default('unknown');
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->string('source')->default('inbound');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['gym_id', 'phone']);
        });

        Schema::create('wa_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['gym_id', 'name']);
        });

        Schema::create('wa_contact_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wa_contact_id')->constrained('wa_contacts')->cascadeOnDelete();
            $table->foreignId('wa_tag_id')->constrained('wa_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['wa_contact_id', 'wa_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_contact_tag');
        Schema::dropIfExists('wa_tags');
        Schema::dropIfExists('wa_contacts');
    }
};
