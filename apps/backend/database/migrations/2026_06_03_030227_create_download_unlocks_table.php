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
        Schema::create('download_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_reference')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('status')->default('unlocked');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['event_photo_id', 'status']);
            $table->index('order_reference');
            $table->index('customer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_unlocks');
    }
};
