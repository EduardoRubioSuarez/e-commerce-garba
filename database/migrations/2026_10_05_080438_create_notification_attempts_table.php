<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_notification_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('claim_token')->unique();
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
    }
};
