<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps a member to the identifier a specific gate device knows them by
     * (its own internal user id for a face template, or a finger slot for a
     * fingerprint template). The biometric template itself never leaves the
     * device — only this mapping and enrolment metadata are stored here.
     */
    public function up(): void
    {
        Schema::create('member_device_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('external_user_id');
            $table->enum('biometric_type', ['face', 'fingerprint']);
            $table->string('finger_position')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('consent_given_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'external_user_id'], 'member_device_identifiers_device_external_unique');
            $table->unique(['member_id', 'device_id', 'biometric_type', 'finger_position'], 'member_device_identifiers_member_device_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_device_identifiers');
    }
};
