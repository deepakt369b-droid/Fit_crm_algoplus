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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->enum('direction', ['in', 'out'])->default('in');
            $table->enum('method', ['face', 'fingerprint', 'manual'])->default('face');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamp('recognized_at');
            $table->string('source')->default('device');
            $table->string('dedupe_hash')->unique();
            $table->timestamps();

            $table->index(['gym_id', 'member_id', 'recognized_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
