<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * gym_id is nullable: a dedicated number belongs to one branch, but a
     * shared number (one WhatsApp line answering for several branches) has
     * gym_id null and is_shared true — GymScope then leaves it unscoped,
     * and which branch a given conversation belongs to is resolved from
     * the contact instead (see wa_contacts).
     */
    public function up(): void
    {
        Schema::create('wa_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained('gyms')->cascadeOnDelete();
            $table->string('waba_id');
            $table->string('phone_number_id')->unique();
            $table->string('display_phone_number')->nullable();
            $table->string('verified_name')->nullable();
            $table->text('access_token')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_phone_numbers');
    }
};
