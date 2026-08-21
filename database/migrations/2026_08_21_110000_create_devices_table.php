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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['face', 'fingerprint', 'hybrid'])->default('face');
            $table->string('location')->nullable();
            $table->string('serial')->nullable();
            $table->enum('status', ['pending', 'paired', 'revoked'])->default('pending');
            $table->string('pairing_code_hash')->nullable();
            $table->timestamp('pairing_expires_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('firmware_version')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['gym_id', 'serial']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
